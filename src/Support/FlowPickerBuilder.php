<?php

namespace JobMetric\Flow\Support;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class FlowPickerBuilder
 *
 * Defines the constraints and selection strategy used by the FlowPicker to choose a Flow.
 * This builder centralizes all filters (subject, environment, channel, versions, rollout),
 * ordering preferences, match strategy, fallback cascade steps, and performance hints
 * (per-request caching). Models using the HasFlow trait should override their own
 * buildFlowPicker() to configure an instance of this builder.
 *
 * @example
 * $builder->subjectType(\App\Models\Board::class)
 *         ->subjectScope((string) $tenantId)
 *         ->subjectCollection('epic')
 *         ->environment('prod')
 *         ->channel('api')
 *         ->onlyActive(true)
 *         ->timeNow(now('UTC'))
 *         ->evaluateRollout(true)
 *         ->rolloutKeyResolver(fn (Model $m) => (string) $m->getAttribute('user_id'))
 *         ->orderByDefault()
 *         ->fallbackCascade([
 *             FlowPickerBuilder::FB_DROP_CHANNEL,
 *             FlowPickerBuilder::FB_DROP_ENVIRONMENT,
 *             FlowPickerBuilder::FB_IGNORE_TIMEWINDOW,
 *         ]);
 */
class FlowPickerBuilder
{
    /**
     * Strategy that returns the best candidate based on ordering rules.
     */
    public const STRATEGY_BEST = 'best';

    /**
     * Strategy that returns the very first matching record (minimal ordering).
     */
    public const STRATEGY_FIRST = 'first';

    /**
     * Fallback step: remove the channel filter.
     */
    public const FB_DROP_CHANNEL = 'drop_channel';

    /**
     * Fallback step: remove the environment filter.
     */
    public const FB_DROP_ENVIRONMENT = 'drop_environment';

    /**
     * Fallback step: keep status, ignore active_from/active_to checks.
     */
    public const FB_IGNORE_TIMEWINDOW = 'ignore_timewindow';

    /**
     * Fallback step: disable rollout gating entirely.
     */
    public const FB_DISABLE_ROLLOUT = 'disable_rollout';

    /**
     * Fallback step: drop the "is_default" requirement.
     */
    public const FB_DROP_REQUIRE_DEFAULT = 'drop_require_default';

    /**
     * Model class name whose flows are being selected; used to partition flows by owner type.
     *
     * @var class-string<Model>|null
     */
    protected ?string $subjectType = null;

    /**
     * Optional subject scope (e.g., tenant/org). Helps partition flows by domain.
     *
     * @var string|null
     */
    protected ?string $subjectScope = null;

    /**
     * Optional subject collection (e.g., epic/story/task) to further partition flows.
     *
     * @var string|null
     */
    protected ?string $subjectCollection = null;

    /**
     * Environment filter (e.g., prod/staging). Null means no environment filtering.
     *
     * @var string|null
     */
    protected ?string $environment = null;

    /**
     * Preferred environments for ordering (not a filter). Earlier items rank higher.
     *
     * @var array<int,string>
     */
    protected array $preferEnvironments = [];

    /**
     * Channel filter (e.g., web/api). Null means no channel filtering.
     *
     * @var string|null
     */
    protected ?string $channel = null;

    /**
     * Preferred channels for ordering (not a filter). Earlier items rank higher.
     *
     * @var array<int,string>
     */
    protected array $preferChannels = [];

    /**
     * When true, enforce status=true and (unless ignored) active window checks.
     *
     * @var bool
     */
    protected bool $onlyActive = true;

    /**
     * When true with onlyActive=true, ignore active_from/active_to but keep status=true.
     *
     * @var bool
     */
    protected bool $ignoreTimeWindow = false;

    /**
     * The reference "now" in UTC used to evaluate active window.
     *
     * @var DateTimeInterface|null
     */
    protected ?DateTimeInterface $nowUtc = null;

    /**
     * When true, apply rollout checks (stable bucket against rollout_pct).
     *
     * @var bool
     */
    protected bool $evaluateRollout = true;

    /**
     * Optional rollout namespace to isolate bucket spaces between domains/features.
     *
     * @var string|null
     */
    protected ?string $rolloutNamespace = null;

    /**
     * Optional rollout salt to further stabilize/segregate hashing.
     *
     * @var string|null
     */
    protected ?string $rolloutSalt = null;

    /**
     * Resolver that returns a stable rollout key per model instance (e.g., user_id).
     *
     * @var null|callable(Model):(?string)
     */
    protected $rolloutKeyResolver = null;

    /**
     * Additional custom WHERE callbacks applied to the query.
     *
     * @var array<int,Closure(Builder,Model):void>
     */
    protected array $whereCallbacks = [];

    /**
     * Optional custom ordering callback. If null, orderByDefault() is used (unless STRATEGY_FIRST).
     *
     * @var Closure(Builder):void|null
     */
    protected $orderingCallback = null;

    /**
     * Selection strategy: STRATEGY_BEST or STRATEGY_FIRST.
     *
     * @var string
     */
    protected string $matchStrategy = self::STRATEGY_BEST;

    /**
     * When true, only flows with is_default=true are eligible.
     *
     * @var bool
     */
    protected bool $requireDefault = false;

    /**
     * Whitelist of flow IDs to include. Empty means no whitelist.
     *
     * @var array<int,int>
     */
    protected array $includeFlowIds = [];

    /**
     * Blacklist of flow IDs to exclude.
     *
     * @var array<int,int>
     */
    protected array $excludeFlowIds = [];

    /**
     * Preferred flow IDs for ordering (not a filter). Earlier items rank higher.
     *
     * @var array<int,int>
     */
    protected array $preferFlowIds = [];

    /**
     * Exact version pin. If set, versionMin/versionMax are ignored.
     *
     * @var int|null
     */
    protected ?int $versionEquals = null;

    /**
     * Minimum allowed version (inclusive), used when versionEquals is null.
     *
     * @var int|null
     */
    protected ?int $versionMin = null;

    /**
     * Maximum allowed version (inclusive), used when versionEquals is null.
     *
     * @var int|null
     */
    protected ?int $versionMax = null;

    /**
     * Ordered list of fallback steps to progressively relax constraints.
     *
     * @var array<int,string>
     */
    protected array $fallbackCascade = [];

    /**
     * Resolver that can force a specific flow_id from the model (with active checks).
     *
     * @var null|callable(Model):(?int)
     */
    protected $forceFlowIdResolver = null;

    /**
     * When true, the consumer intends to fetch candidates; pick() still returns a single Flow.
     *
     * @var bool
     */
    protected bool $returnCandidates = false;

    /**
     * Optional limit applied when fetching candidates.
     *
     * @var int|null
     */
    protected ?int $candidatesLimit = null;

    /**
     * Enables per-request memoization when safe (no dynamic callbacks).
     *
     * @var bool
     */
    protected bool $cacheInRequest = false;

    /**
     * Set the subject type (model class) to partition flows by owner type.
     *
     * @param class-string<Model> $class Model class string.
     *
     * @return $this
     */
    public function subjectType(string $class): self
    {
        $this->subjectType = $class;

        return $this;
    }

    /**
     * Set an optional subject scope (tenant/org/etc.) to further partition flows.
     *
     * @param string|null $scope Scope identifier or null to clear.
     *
     * @return $this
     */
    public function subjectScope(?string $scope): self
    {
        $this->subjectScope = $scope;

        return $this;
    }

    /**
     * Set an optional subject collection (e.g., epic/story/task) to further partition flows.
     *
     * @param string|null $collection Collection identifier or null to clear.
     *
     * @return $this
     */
    public function subjectCollection(?string $collection): self
    {
        $this->subjectCollection = $collection;

        return $this;
    }

    /**
     * Restrict flows to the given environment (e.g., prod/staging). Null disables the filter.
     *
     * @param string|null $environment Environment name or null.
     *
     * @return $this
     */
    public function environment(?string $environment): self
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * Restrict flows to the given channel (e.g., web/api). Null disables the filter.
     *
     * @param string|null $channel Channel name or null.
     *
     * @return $this
     */
    public function channel(?string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * Require active flows. When true, status=true and, unless ignored, time window checks apply.
     *
     * @param bool $onlyActive Whether to enforce active constraints.
     *
     * @return $this
     */
    public function onlyActive(bool $onlyActive): self
    {
        $this->onlyActive = $onlyActive;

        return $this;
    }

    /**
     * Ignore active_from/active_to checks (keeps status=true if onlyActive is true).
     *
     * @param bool $ignore Whether to ignore time window checks.
     *
     * @return $this
     */
    public function ignoreTimeWindow(bool $ignore = true): self
    {
        $this->ignoreTimeWindow = $ignore;

        return $this;
    }

    /**
     * Set the reference "now" (UTC) to evaluate time windows deterministically.
     *
     * @param DateTimeInterface $now UTC instant for time comparisons.
     *
     * @return $this
     */
    public function timeNow(DateTimeInterface $now): self
    {
        $this->nowUtc = $now;

        return $this;
    }

    /**
     * Enable or disable rollout gating.
     *
     * @param bool $enabled Whether rollout should be applied.
     *
     * @return $this
     */
    public function evaluateRollout(bool $enabled): self
    {
        $this->evaluateRollout = $enabled;

        return $this;
    }

    /**
     * Set a namespace to isolate rollout bucket hashing across domains/features.
     *
     * @param string|null $ns Namespace string or null to clear.
     *
     * @return $this
     */
    public function rolloutNamespace(?string $ns): self
    {
        $this->rolloutNamespace = $ns;

        return $this;
    }

    /**
     * Set a salt to further stabilize or segregate rollout hashing.
     *
     * @param string|null $salt Salt string or null to clear.
     *
     * @return $this
     */
    public function rolloutSalt(?string $salt): self
    {
        $this->rolloutSalt = $salt;

        return $this;
    }

    /**
     * Provide a resolver for the stable rollout key (e.g., user_id, order_id).
     *
     * @param callable(Model):(?string) $resolver Callback that returns a stable key or null.
     *
     * @return $this
     */
    public function rolloutKeyResolver(callable $resolver): self
    {
        $this->rolloutKeyResolver = $resolver;

        return $this;
    }

    /**
     * Attach a custom WHERE callback to extend query constraints.
     *
     * @param Closure(Builder,Model):void $callback Callback receiving the query and model.
     *
     * @return $this
     */
    public function where(Closure $callback): self
    {
        $this->whereCallbacks[] = $callback;

        return $this;
    }

    /**
     * Apply the default ordering:
     * version DESC, is_default DESC, ordering DESC, id DESC.
     *
     * @return $this
     */
    public function orderByDefault(): self
    {
        $this->orderingCallback = static function (Builder $q): void {
            $q->orderByDesc('version')
                ->orderByDesc('is_default')
                ->orderByDesc('ordering')
                ->orderByDesc('id');
        };

        return $this;
    }

    /**
     * Provide a custom ORDER BY callback.
     *
     * @param Closure(Builder):void $callback Ordering callback.
     *
     * @return $this
     */
    public function orderBy(Closure $callback): self
    {
        $this->orderingCallback = $callback;

        return $this;
    }

    /**
     * Set selection strategy (STRATEGY_BEST or STRATEGY_FIRST).
     *
     * @param string $strategy Strategy constant.
     *
     * @return $this
     */
    public function matchStrategy(string $strategy): self
    {
        $this->matchStrategy = $strategy;

        return $this;
    }

    /**
     * Convenience to switch to "first match" strategy; false reverts to "best".
     *
     * @param bool $enabled Whether to pick the first match.
     *
     * @return $this
     */
    public function pickFirstMatch(bool $enabled = true): self
    {
        $this->matchStrategy = $enabled ? self::STRATEGY_FIRST : self::STRATEGY_BEST;

        return $this;
    }

    /**
     * Require flows with is_default=true.
     *
     * @param bool $required Whether to require is_default=true.
     *
     * @return $this
     */
    public function requireDefault(bool $required = true): self
    {
        $this->requireDefault = $required;

        return $this;
    }

    /**
     * Restrict selection to only these flow IDs (whitelist).
     *
     * @param array<int,int> $ids List of allowed IDs.
     *
     * @return $this
     */
    public function includeFlows(array $ids): self
    {
        $this->includeFlowIds = array_values(array_unique(array_map('intval', $ids)));

        return $this;
    }

    /**
     * Exclude these flow IDs (blacklist).
     *
     * @param array<int,int> $ids List of disallowed IDs.
     *
     * @return $this
     */
    public function excludeFlows(array $ids): self
    {
        $this->excludeFlowIds = array_values(array_unique(array_map('intval', $ids)));

        return $this;
    }

    /**
     * Prefer these flow IDs in ordering (not filtering). Earlier items rank higher.
     *
     * @param array<int,int> $ids Preferred IDs.
     *
     * @return $this
     */
    public function preferFlows(array $ids): self
    {
        $this->preferFlowIds = array_values(array_unique(array_map('intval', $ids)));

        return $this;
    }

    /**
     * Pin selection to an exact version (disables versionMin/versionMax).
     *
     * @param int $version Exact version.
     *
     * @return $this
     */
    public function versionEquals(int $version): self
    {
        $this->versionEquals = $version;

        return $this;
    }

    /**
     * Set a minimum version (inclusive). Ignored when versionEquals is set.
     *
     * @param int $min Minimum version.
     *
     * @return $this
     */
    public function versionAtLeast(int $min): self
    {
        $this->versionMin = $min;

        return $this;
    }

    /**
     * Set a maximum version (inclusive). Ignored when versionEquals is set.
     *
     * @param int $max Maximum version.
     *
     * @return $this
     */
    public function versionAtMost(int $max): self
    {
        $this->versionMax = $max;

        return $this;
    }

    /**
     * Provide preferred environments for ordering (not filtering).
     * Earlier list items receive higher ordering weight.
     *
     * @param array<int,string> $envs Preferred environments.
     *
     * @return $this
     */
    public function preferEnvironments(array $envs): self
    {
        $this->preferEnvironments = array_values(array_filter(array_map('strval', $envs)));

        return $this;
    }

    /**
     * Provide preferred channels for ordering (not filtering).
     * Earlier list items receive higher ordering weight.
     *
     * @param array<int,string> $channels Preferred channels.
     *
     * @return $this
     */
    public function preferChannels(array $channels): self
    {
        $this->preferChannels = array_values(array_filter(array_map('strval', $channels)));

        return $this;
    }

    /**
     * Define an ordered list of fallback steps to progressively relax constraints.
     * Only recognized FB_* constants are kept; others are ignored.
     *
     * @param array<int,string> $steps Ordered fallback steps.
     *
     * @return $this
     */
    public function fallbackCascade(array $steps): self
    {
        $valid = [
            self::FB_DROP_CHANNEL,
            self::FB_DROP_ENVIRONMENT,
            self::FB_IGNORE_TIMEWINDOW,
            self::FB_DISABLE_ROLLOUT,
            self::FB_DROP_REQUIRE_DEFAULT,
        ];

        $this->fallbackCascade = array_values(array_intersect($steps, $valid));

        return $this;
    }

    /**
     * Provide a resolver that can force a given flow_id from the model (if active/valid).
     *
     * @param callable(Model):(?int) $resolver Callback returning a flow ID or null.
     *
     * @return $this
     */
    public function forceFlowIdResolver(callable $resolver): self
    {
        $this->forceFlowIdResolver = $resolver;

        return $this;
    }

    /**
     * Hint that the consumer intends to fetch candidates (for diagnostics).
     * pick() still returns a single Flow; use FlowPicker::candidates() to fetch the list.
     *
     * @param bool $on Whether to enable the hint.
     *
     * @return $this
     */
    public function returnCandidates(bool $on = true): self
    {
        $this->returnCandidates = $on;

        return $this;
    }

    /**
     * Limit the number of candidates returned by FlowPicker::candidates().
     *
     * @param int|null $limit Positive integer or null for no limit.
     *
     * @return $this
     */
    public function candidatesLimit(?int $limit): self
    {
        $this->candidatesLimit = $limit;

        return $this;
    }

    /**
     * Enable simple per-request memoization when no dynamic callbacks are present.
     *
     * @param bool $on Whether to enable memoization.
     *
     * @return $this
     */
    public function cacheInRequest(bool $on = true): self
    {
        $this->cacheInRequest = $on;

        return $this;
    }

    /**
     * Get the subject type (model class) used to partition flows.
     *
     * @return class-string<Model>|null
     */
    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    /**
     * Get the optional subject scope.
     *
     * @return string|null
     */
    public function getSubjectScope(): ?string
    {
        return $this->subjectScope;
    }

    /**
     * Get the optional subject collection.
     *
     * @return string|null
     */
    public function getSubjectCollection(): ?string
    {
        return $this->subjectCollection;
    }

    /**
     * Get the environment filter.
     *
     * @return string|null
     */
    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    /**
     * Get the channel filter.
     *
     * @return string|null
     */
    public function getChannel(): ?string
    {
        return $this->channel;
    }

    /**
     * Get preferred environments for ordering (not filtering).
     *
     * @return array<int,string>
     */
    public function getPreferEnvironments(): array
    {
        return $this->preferEnvironments;
    }

    /**
     * Get preferred channels for ordering (not filtering).
     *
     * @return array<int,string>
     */
    public function getPreferChannels(): array
    {
        return $this->preferChannels;
    }

    /**
     * Determine whether active constraints are enforced.
     *
     * @return bool
     */
    public function isOnlyActive(): bool
    {
        return $this->onlyActive;
    }

    /**
     * Determine whether time window checks are ignored (status may still be enforced).
     *
     * @return bool
     */
    public function shouldIgnoreTimeWindow(): bool
    {
        return $this->ignoreTimeWindow;
    }

    /**
     * Get the reference UTC time used for window evaluation.
     *
     * @return DateTimeInterface|null
     */
    public function getNowUtc(): ?DateTimeInterface
    {
        return $this->nowUtc;
    }

    /**
     * Determine whether rollout gating is applied.
     *
     * @return bool
     */
    public function shouldEvaluateRollout(): bool
    {
        return $this->evaluateRollout;
    }

    /**
     * Get the rollout namespace.
     *
     * @return string|null
     */
    public function getRolloutNamespace(): ?string
    {
        return $this->rolloutNamespace;
    }

    /**
     * Get the rollout salt.
     *
     * @return string|null
     */
    public function getRolloutSalt(): ?string
    {
        return $this->rolloutSalt;
    }

    /**
     * Get the rollout key resolver.
     *
     * @return callable(Model):(?string)|null
     */
    public function getRolloutKeyResolver(): ?callable
    {
        return $this->rolloutKeyResolver;
    }

    /**
     * Get the custom WHERE callbacks.
     *
     * @return array<int,Closure(Builder,Model):void>
     */
    public function getWhereCallbacks(): array
    {
        return $this->whereCallbacks;
    }

    /**
     * Get the custom ORDER BY callback.
     *
     * @return Closure(Builder):void|null
     */
    public function getOrderingCallback(): ?Closure
    {
        return $this->orderingCallback;
    }

    /**
     * Get the current selection strategy.
     *
     * @return string
     */
    public function getMatchStrategy(): string
    {
        return $this->matchStrategy;
    }

    /**
     * Determine whether only is_default flows are allowed.
     *
     * @return bool
     */
    public function isRequireDefault(): bool
    {
        return $this->requireDefault;
    }

    /**
     * Get the whitelist of flow IDs.
     *
     * @return array<int,int>
     */
    public function getIncludeFlowIds(): array
    {
        return $this->includeFlowIds;
    }

    /**
     * Get the blacklist of flow IDs.
     *
     * @return array<int,int>
     */
    public function getExcludeFlowIds(): array
    {
        return $this->excludeFlowIds;
    }

    /**
     * Get the preferred flow IDs for ordering.
     *
     * @return array<int,int>
     */
    public function getPreferFlowIds(): array
    {
        return $this->preferFlowIds;
    }

    /**
     * Get the exact version pin, if any.
     *
     * @return int|null
     */
    public function getVersionEquals(): ?int
    {
        return $this->versionEquals;
    }

    /**
     * Get the minimum version (inclusive), if any.
     *
     * @return int|null
     */
    public function getVersionMin(): ?int
    {
        return $this->versionMin;
    }

    /**
     * Get the maximum version (inclusive), if any.
     *
     * @return int|null
     */
    public function getVersionMax(): ?int
    {
        return $this->versionMax;
    }

    /**
     * Get the ordered fallback cascade steps.
     *
     * @return array<int,string>
     */
    public function getFallbackCascade(): array
    {
        return $this->fallbackCascade;
    }

    /**
     * Get the forced flow ID resolver.
     *
     * @return callable(Model):(?int)|null
     */
    public function getForceFlowIdResolver(): ?callable
    {
        return $this->forceFlowIdResolver;
    }

    /**
     * Determine whether the consumer intends to fetch candidates.
     *
     * @return bool
     */
    public function shouldReturnCandidates(): bool
    {
        return $this->returnCandidates;
    }

    /**
     * Get the candidates limit.
     *
     * @return int|null
     */
    public function getCandidatesLimit(): ?int
    {
        return $this->candidatesLimit;
    }

    /**
     * Determine whether per-request memoization is enabled (when safe).
     *
     * @return bool
     */
    public function shouldCacheInRequest(): bool
    {
        return $this->cacheInRequest;
    }
}
