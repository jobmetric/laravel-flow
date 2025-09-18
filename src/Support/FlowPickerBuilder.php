<?php

namespace JobMetric\Flow\Support;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class FlowPickerBuilder
 *
 * Configures constraints and strategy for Flow selection.
 * Key features:
 *  - subjectType / subjectScope
 *  - environment / channel (+ prefer lists)
 *  - onlyActive + time window handling (with ignore option)
 *  - rollout evaluation (on/off) + namespace/salt + custom key resolver
 *  - include/exclude/prefer specific flow ids
 *  - requireDefault, version pin/range
 *  - match strategy: "best" (default ordering) or "first" (first match)
 *  - custom where/order callbacks
 *  - fallback cascade with relaxable steps
 *  - optional forced flow resolver from model
 *  - candidates limit and ability to fetch candidates list
 *  - optional per-request caching when cacheable
 */
class FlowPickerBuilder
{
    public const STRATEGY_BEST = 'best';
    public const STRATEGY_FIRST = 'first';

    // Fallback cascade steps
    public const FB_DROP_CHANNEL = 'drop_channel';
    public const FB_DROP_ENVIRONMENT = 'drop_environment';
    public const FB_IGNORE_TIMEWINDOW = 'ignore_timewindow';
    public const FB_DISABLE_ROLLOUT = 'disable_rollout';
    public const FB_DROP_REQUIRE_DEFAULT = 'drop_require_default';

    /** @var class-string<Model>|null */
    protected ?string $subjectType = null;

    /** @var string|null */
    protected ?string $subjectScope = null;

    /** @var string|null */
    protected ?string $environment = null;

    /** @var string[] */
    protected array $preferEnvironments = [];

    /** @var string|null */
    protected ?string $channel = null;

    /** @var string[] */
    protected array $preferChannels = [];

    /** @var bool */
    protected bool $onlyActive = true;

    /** @var bool */
    protected bool $ignoreTimeWindow = false;

    /** @var DateTimeInterface|null */
    protected ?DateTimeInterface $nowUtc = null;

    /** @var bool */
    protected bool $evaluateRollout = true;

    /** @var string|null */
    protected ?string $rolloutNamespace = null;

    /** @var string|null */
    protected ?string $rolloutSalt = null;

    /** @var null|callable(Model):(?string) */
    protected $rolloutKeyResolver = null;

    /** @var array<int,Closure(Builder,Model):void> */
    protected array $whereCallbacks = [];

    /** @var Closure(Builder):void|null */
    protected $orderingCallback = null;

    /** @var string */
    protected string $matchStrategy = self::STRATEGY_BEST;

    /** @var bool */
    protected bool $requireDefault = false;

    /** @var int[] */
    protected array $includeFlowIds = [];

    /** @var int[] */
    protected array $excludeFlowIds = [];

    /** @var int[] */
    protected array $preferFlowIds = [];

    /** @var int|null */
    protected ?int $versionEquals = null;

    /** @var int|null */
    protected ?int $versionMin = null;

    /** @var int|null */
    protected ?int $versionMax = null;

    /** @var string[] */
    protected array $fallbackCascade = [];

    /** @var null|callable(Model):(?int) */
    protected $forceFlowIdResolver = null;

    /** @var bool */
    protected bool $returnCandidates = false;

    /** @var int|null */
    protected ?int $candidatesLimit = null;

    /** @var bool */
    protected bool $cacheInRequest = false;

    // Base constraints

    /**
     * @param class-string<Model> $class
     * @return $this
     */
    public function subjectType(string $class): self
    {
        $this->subjectType = $class;
        return $this;
    }

    public function subjectScope(?string $scope): self
    {
        $this->subjectScope = $scope;
        return $this;
    }

    public function environment(?string $environment): self
    {
        $this->environment = $environment;
        return $this;
    }

    public function channel(?string $channel): self
    {
        $this->channel = $channel;
        return $this;
    }

    // Active window & time
    public function onlyActive(bool $onlyActive): self
    {
        $this->onlyActive = $onlyActive;
        return $this;
    }

    public function ignoreTimeWindow(bool $ignore = true): self
    {
        $this->ignoreTimeWindow = $ignore;
        return $this;
    }

    public function timeNow(DateTimeInterface $now): self
    {
        $this->nowUtc = $now;
        return $this;
    }

    // Rollout
    public function evaluateRollout(bool $enabled): self
    {
        $this->evaluateRollout = $enabled;
        return $this;
    }

    public function rolloutNamespace(?string $ns): self
    {
        $this->rolloutNamespace = $ns;
        return $this;
    }

    public function rolloutSalt(?string $salt): self
    {
        $this->rolloutSalt = $salt;
        return $this;
    }

    /**
     * @param callable(Model):(?string) $resolver
     * @return $this
     */
    public function rolloutKeyResolver(callable $resolver): self
    {
        $this->rolloutKeyResolver = $resolver;
        return $this;
    }

    // Custom filters & ordering

    /**
     * @param Closure(Builder,Model):void $callback
     * @return $this
     */
    public function where(Closure $callback): self
    {
        $this->whereCallbacks[] = $callback;
        return $this;
    }

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
     * @param Closure(Builder):void $callback
     * @return $this
     */
    public function orderBy(Closure $callback): self
    {
        $this->orderingCallback = $callback;
        return $this;
    }

    // Strategy
    public function matchStrategy(string $strategy): self
    {
        $this->matchStrategy = $strategy;
        return $this;
    }

    public function pickFirstMatch(bool $enabled = true): self
    {
        $this->matchStrategy = $enabled ? self::STRATEGY_FIRST : self::STRATEGY_BEST;
        return $this;
    }

    // Flags & ranges
    public function requireDefault(bool $required = true): self
    {
        $this->requireDefault = $required;
        return $this;
    }

    public function includeFlows(array $ids): self
    {
        $this->includeFlowIds = array_values(array_unique(array_map('intval', $ids)));
        return $this;
    }

    public function excludeFlows(array $ids): self
    {
        $this->excludeFlowIds = array_values(array_unique(array_map('intval', $ids)));
        return $this;
    }

    public function preferFlows(array $ids): self
    {
        $this->preferFlowIds = array_values(array_unique(array_map('intval', $ids)));
        return $this;
    }

    public function versionEquals(int $version): self
    {
        $this->versionEquals = $version;
        return $this;
    }

    public function versionAtLeast(int $min): self
    {
        $this->versionMin = $min;
        return $this;
    }

    public function versionAtMost(int $max): self
    {
        $this->versionMax = $max;
        return $this;
    }

    // Prefer environment/channel
    public function preferEnvironments(array $envs): self
    {
        $this->preferEnvironments = array_values(array_filter(array_map('strval', $envs)));
        return $this;
    }

    public function preferChannels(array $channels): self
    {
        $this->preferChannels = array_values(array_filter(array_map('strval', $channels)));
        return $this;
    }

    // Fallback cascade

    /**
     * @param string[] $steps Use constants FB_*
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

    // Force flow from model

    /**
     * @param callable(Model):(?int) $resolver Returns flow_id or null
     * @return $this
     */
    public function forceFlowIdResolver(callable $resolver): self
    {
        $this->forceFlowIdResolver = $resolver;
        return $this;
    }

    // Candidates / limit / caching
    public function returnCandidates(bool $on = true): self
    {
        $this->returnCandidates = $on;
        return $this;
    }

    public function candidatesLimit(?int $limit): self
    {
        $this->candidatesLimit = $limit;
        return $this;
    }

    public function cacheInRequest(bool $on = true): self
    {
        $this->cacheInRequest = $on;
        return $this;
    }

    // Getters
    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function getSubjectScope(): ?string
    {
        return $this->subjectScope;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    /** @return string[] */
    public function getPreferEnvironments(): array
    {
        return $this->preferEnvironments;
    }

    /** @return string[] */
    public function getPreferChannels(): array
    {
        return $this->preferChannels;
    }

    public function isOnlyActive(): bool
    {
        return $this->onlyActive;
    }

    public function shouldIgnoreTimeWindow(): bool
    {
        return $this->ignoreTimeWindow;
    }

    public function getNowUtc(): ?DateTimeInterface
    {
        return $this->nowUtc;
    }

    public function shouldEvaluateRollout(): bool
    {
        return $this->evaluateRollout;
    }

    public function getRolloutNamespace(): ?string
    {
        return $this->rolloutNamespace;
    }

    public function getRolloutSalt(): ?string
    {
        return $this->rolloutSalt;
    }

    /** @return callable(Model):(?string)|null */
    public function getRolloutKeyResolver(): ?callable
    {
        return $this->rolloutKeyResolver;
    }

    /** @return array<int,Closure(Builder,Model):void> */
    public function getWhereCallbacks(): array
    {
        return $this->whereCallbacks;
    }

    /** @return Closure(Builder):void|null */
    public function getOrderingCallback(): ?Closure
    {
        return $this->orderingCallback;
    }

    public function getMatchStrategy(): string
    {
        return $this->matchStrategy;
    }

    public function isRequireDefault(): bool
    {
        return $this->requireDefault;
    }

    /** @return int[] */
    public function getIncludeFlowIds(): array
    {
        return $this->includeFlowIds;
    }

    /** @return int[] */
    public function getExcludeFlowIds(): array
    {
        return $this->excludeFlowIds;
    }

    /** @return int[] */
    public function getPreferFlowIds(): array
    {
        return $this->preferFlowIds;
    }

    public function getVersionEquals(): ?int
    {
        return $this->versionEquals;
    }

    public function getVersionMin(): ?int
    {
        return $this->versionMin;
    }

    public function getVersionMax(): ?int
    {
        return $this->versionMax;
    }

    /** @return string[] */
    public function getFallbackCascade(): array
    {
        return $this->fallbackCascade;
    }

    /** @return callable(Model):(?int)|null */
    public function getForceFlowIdResolver(): ?callable
    {
        return $this->forceFlowIdResolver;
    }

    public function shouldReturnCandidates(): bool
    {
        return $this->returnCandidates;
    }

    public function getCandidatesLimit(): ?int
    {
        return $this->candidatesLimit;
    }

    public function shouldCacheInRequest(): bool
    {
        return $this->cacheInRequest;
    }
}
