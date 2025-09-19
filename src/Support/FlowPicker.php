<?php

namespace JobMetric\Flow\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use JobMetric\Flow\Models\Flow;

/**
 * Class FlowPicker
 *
 * Executes the selection process using a configured FlowPickerBuilder.
 * - Filters by subject type/scope, environment, channel.
 * - Enforces active/time-window constraints (configurable).
 * - Applies rollout gates (configurable).
 * - Supports include/exclude/prefer lists and version constraints.
 * - Honors match strategy (BEST/FIRST) and fallback cascade.
 * - Provides a candidates() helper for diagnostics/insight.
 */
class FlowPicker
{
    /**
     * Per-request memoization store (safe only when the builder has no dynamic callbacks).
     *
     * @var array<string,mixed>
     */
    protected static array $requestCache = [];

    /**
     * Pick a single Flow according to the builder's strategy.
     * Applies fallback cascade when no candidates are found initially.
     *
     * @param Model $model Flowable model instance.
     * @param FlowPickerBuilder $builder Configured builder.
     *
     * @return Flow|null
     */
    public function pick(Model $model, FlowPickerBuilder $builder): ?Flow
    {
        // Forced flow (if provided and valid under active checks).
        if ($flow = $this->resolveForcedFlow($model, $builder)) {
            return $flow;
        }

        // Return from per-request cache if available.
        if ($builder->shouldCacheInRequest()) {
            $cacheKey = $this->computeCacheKey($model, $builder);
            if ($cacheKey !== null && array_key_exists($cacheKey, self::$requestCache)) {
                return self::$requestCache[$cacheKey] instanceof Flow
                    ? self::$requestCache[$cacheKey]
                    : (self::$requestCache[$cacheKey][0] ?? null);
            }
        }

        // Primary attempt.
        $candidates = $this->candidates($model, $builder);
        $picked = $candidates->first();

        // Fallback cascade if nothing matched.
        if (!$picked && $builder->getFallbackCascade()) {
            $picked = $this->applyFallbackCascade($model, $builder);
        }

        // Store in per-request cache (if applicable).
        if ($builder->shouldCacheInRequest()) {
            $cacheKey = $this->computeCacheKey($model, $builder);
            if ($cacheKey !== null) {
                self::$requestCache[$cacheKey] = $picked;
            }
        }

        return $picked;
    }

    /**
     * Build and execute the candidate query according to the current builder (no fallback).
     *
     * @param Model $model Flowable model instance.
     * @param FlowPickerBuilder $builder Configured builder.
     *
     * @return Collection<int,Flow>
     */
    public function candidates(Model $model, FlowPickerBuilder $builder): Collection
    {
        $flowTable = Config::get('workflow.tables.flow', 'flows');
        $q = Flow::query();

        // Subject filters.
        if ($builder->getSubjectType() !== null) {
            $q->where("{$flowTable}.subject_type", $builder->getSubjectType());
        }
        if ($builder->getSubjectScope() !== null) {
            $q->where("{$flowTable}.subject_scope", $builder->getSubjectScope());
        }

        // Environment/channel filters.
        if ($builder->getEnvironment() !== null) {
            $q->where("{$flowTable}.environment", $builder->getEnvironment());
        }
        if ($builder->getChannel() !== null) {
            $q->where("{$flowTable}.channel", $builder->getChannel());
        }

        // Include / exclude Flow IDs.
        $includeIds = $builder->getIncludeFlowIds();
        if (!empty($includeIds)) {
            $q->whereIn("{$flowTable}.id", $includeIds);
        }
        $excludeIds = $builder->getExcludeFlowIds();
        if (!empty($excludeIds)) {
            $q->whereNotIn("{$flowTable}.id", $excludeIds);
        }

        // Active/time-window constraints.
        if ($builder->isOnlyActive()) {
            if (!$builder->shouldIgnoreTimeWindow()) {
                $now = $builder->getNowUtc() ?? Carbon::now('UTC');

                $q->where("{$flowTable}.status", true)
                    ->where(function (Builder $w) use ($flowTable, $now) {
                        $w->whereNull("{$flowTable}.active_from")
                            ->orWhere("{$flowTable}.active_from", '<=', $now);
                    })
                    ->where(function (Builder $w) use ($flowTable, $now) {
                        $w->whereNull("{$flowTable}.active_to")
                            ->orWhere("{$flowTable}.active_to", '>=', $now);
                    });
            } else {
                $q->where("{$flowTable}.status", true);
            }
        }

        // Default-only constraint.
        if ($builder->isRequireDefault()) {
            $q->where("{$flowTable}.is_default", true);
        }

        // Version constraints.
        if (($v = $builder->getVersionEquals()) !== null) {
            $q->where("{$flowTable}.version", $v);
        } else {
            if (($min = $builder->getVersionMin()) !== null) {
                $q->where("{$flowTable}.version", '>=', $min);
            }
            if (($max = $builder->getVersionMax()) !== null) {
                $q->where("{$flowTable}.version", '<=', $max);
            }
        }

        // Rollout gating.
        if ($builder->shouldEvaluateRollout()) {
            $rolloutKey = null;
            $resolver = $builder->getRolloutKeyResolver();
            if ($resolver !== null) {
                $rolloutKey = $resolver($model);
            }

            if ($rolloutKey !== null && $rolloutKey !== '') {
                $bucket = $this->stableBucket(
                    $builder->getRolloutNamespace(),
                    $builder->getRolloutSalt(),
                    $rolloutKey
                );

                $q->where(function (Builder $w) use ($flowTable, $bucket) {
                    $w->whereNull("{$flowTable}.rollout_pct")
                        ->orWhere("{$flowTable}.rollout_pct", '>=', $bucket);
                });
            } else {
                // No rollout key: be conservative and accept only flows without rollout gate.
                $q->whereNull("{$flowTable}.rollout_pct");
            }
        }

        // Model-provided custom filters.
        foreach ($builder->getWhereCallbacks() as $callback) {
            $callback($q, $model);
        }

        // Preferential ordering bumps (not filters).
        $this->applyPreferIdsOrdering($q, $flowTable, $builder->getPreferFlowIds());
        $this->applyPreferStringOrdering($q, $flowTable, 'environment', $builder->getPreferEnvironments());
        $this->applyPreferStringOrdering($q, $flowTable, 'channel', $builder->getPreferChannels());

        // Final ordering / strategy.
        if ($builder->getMatchStrategy() === FlowPickerBuilder::STRATEGY_FIRST) {
            $q->orderBy("{$flowTable}.id", 'asc');
        } else {
            $ordering = $builder->getOrderingCallback();
            if ($ordering !== null) {
                $ordering($q);
            } else {
                $q->orderByDesc("{$flowTable}.version")
                    ->orderByDesc("{$flowTable}.is_default")
                    ->orderByDesc("{$flowTable}.ordering")
                    ->orderByDesc("{$flowTable}.id");
            }
        }

        if (($limit = $builder->getCandidatesLimit()) !== null && $limit > 0) {
            $q->limit($limit);
        }

        return $q->get();
    }

    /**
     * Apply fallback steps progressively until a candidate is found or steps are exhausted.
     *
     * @param Model $model Flowable model instance.
     * @param FlowPickerBuilder $original Original builder to clone/relax.
     *
     * @return Flow|null
     */
    protected function applyFallbackCascade(Model $model, FlowPickerBuilder $original): ?Flow
    {
        $steps = $original->getFallbackCascade();
        if (!$steps) {
            return null;
        }

        $builder = clone $original;

        foreach ($steps as $step) {
            switch ($step) {
                case FlowPickerBuilder::FB_DROP_CHANNEL:
                    $builder->channel(null);
                    break;
                case FlowPickerBuilder::FB_DROP_ENVIRONMENT:
                    $builder->environment(null);
                    break;
                case FlowPickerBuilder::FB_IGNORE_TIMEWINDOW:
                    $builder->ignoreTimeWindow(true);
                    break;
                case FlowPickerBuilder::FB_DISABLE_ROLLOUT:
                    $builder->evaluateRollout(false);
                    break;
                case FlowPickerBuilder::FB_DROP_REQUIRE_DEFAULT:
                    $builder->requireDefault(false);
                    break;
            }

            $candidates = $this->candidates($model, $builder);
            if ($candidates->isNotEmpty()) {
                return $candidates->first();
            }
        }

        return null;
    }

    /**
     * Resolve a forced Flow from the model and validate its active/time constraints when enabled.
     *
     * @param Model $model Flowable model instance.
     * @param FlowPickerBuilder $builder Configured builder.
     *
     * @return Flow|null
     */
    protected function resolveForcedFlow(Model $model, FlowPickerBuilder $builder): ?Flow
    {
        $resolver = $builder->getForceFlowIdResolver();
        if ($resolver === null) {
            return null;
        }

        $flowId = $resolver($model);
        if ($flowId === null) {
            return null;
        }

        /** @var Flow|null $flow */
        $flow = Flow::query()->find($flowId);
        if (!$flow) {
            return null;
        }

        if ($builder->isOnlyActive()) {
            if (!$builder->shouldIgnoreTimeWindow()) {
                $now = $builder->getNowUtc() ?? Carbon::now('UTC');
                $isActive = $flow->status
                    && (is_null($flow->active_from) || $flow->active_from <= $now)
                    && (is_null($flow->active_to) || $flow->active_to >= $now);

                if (!$isActive) {
                    return null;
                }
            } else {
                if (!$flow->status) {
                    return null;
                }
            }
        }

        return $flow;
    }

    /**
     * Apply CASE-based ordering boost for a list of preferred Flow IDs.
     *
     * @param Builder $q Query builder.
     * @param string $table Flow table name.
     * @param array<int,int> $ids Preferred IDs (earlier => higher rank).
     *
     * @return void
     */
    protected function applyPreferIdsOrdering(Builder $q, string $table, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $case = 'CASE';
        foreach ($ids as $idx => $id) {
            $rank = 1000 - $idx;
            $case .= ' WHEN ' . (int)$id . ' = ' . "{$table}.id THEN {$rank}";
        }
        $case .= ' ELSE 0 END';

        $q->orderByRaw("({$case}) DESC");
    }

    /**
     * Apply CASE-based ordering boost for a string column (e.g., environment/channel).
     *
     * @param Builder $q Query builder.
     * @param string $table Flow table name.
     * @param string $column Column name.
     * @param array<int,string> $values Preferred values (earlier => higher rank).
     *
     * @return void
     */
    protected function applyPreferStringOrdering(Builder $q, string $table, string $column, array $values): void
    {
        if (empty($values)) {
            return;
        }

        $caseParts = [];
        foreach ($values as $idx => $value) {
            $rank = 1000 - $idx;
            $quoted = DB::getPdo()->quote($value);
            $caseParts[] = "WHEN {$quoted} = {$table}.{$column} THEN {$rank}";
        }

        $case = 'CASE ' . implode(' ', $caseParts) . ' ELSE 0 END';
        $q->orderByRaw("({$case}) DESC");
    }

    /**
     * Compute a stable rollout bucket in the range 0..99 from the given components.
     *
     * @param string|null $namespace Optional namespace to isolate domains.
     * @param string|null $salt Optional salt to segregate hashing.
     * @param string $key Stable rollout key (e.g., user_id).
     *
     * @return int
     */
    protected function stableBucket(?string $namespace, ?string $salt, string $key): int
    {
        $compound = ($namespace ?? '') . '|' . ($salt ?? '') . '|' . $key;
        $hash = crc32($compound);
        $bucket = $hash % 100;

        return (int)$bucket;
    }

    /**
     * Build a cache key for per-request memoization.
     * Returns null when dynamic callbacks prevent safe caching.
     *
     * @param Model $model Flowable model instance.
     * @param FlowPickerBuilder $builder Configured builder.
     *
     * @return string|null
     */
    protected function computeCacheKey(Model $model, FlowPickerBuilder $builder): ?string
    {
        if ($builder->getWhereCallbacks() || $builder->getOrderingCallback()) {
            return null;
        }

        $resolver = $builder->getRolloutKeyResolver();
        $rolloutKey = $resolver ? (string)($resolver($model) ?? '') : '';

        return implode('|', [
            'class=' . get_class($model),
            'id=' . (string)$model->getKey(),
            'stype=' . ($builder->getSubjectType() ?? ''),
            'sscope=' . ($builder->getSubjectScope() ?? ''),
            'env=' . ($builder->getEnvironment() ?? ''),
            'chan=' . ($builder->getChannel() ?? ''),
            'only=' . ($builder->isOnlyActive() ? '1' : '0'),
            'ignTW=' . ($builder->shouldIgnoreTimeWindow() ? '1' : '0'),
            'evalRO=' . ($builder->shouldEvaluateRollout() ? '1' : '0'),
            'rNs=' . ($builder->getRolloutNamespace() ?? ''),
            'rSalt=' . ($builder->getRolloutSalt() ?? ''),
            'rKey=' . $rolloutKey,
            'reqDef=' . ($builder->isRequireDefault() ? '1' : '0'),
            'vEq=' . (string)($builder->getVersionEquals() ?? ''),
            'vMin=' . (string)($builder->getVersionMin() ?? ''),
            'vMax=' . (string)($builder->getVersionMax() ?? ''),
            'inc=' . implode(',', $builder->getIncludeFlowIds()),
            'exc=' . implode(',', $builder->getExcludeFlowIds()),
            'prefIds=' . implode(',', $builder->getPreferFlowIds()),
            'prefEnv=' . implode(',', $builder->getPreferEnvironments()),
            'prefCh=' . implode(',', $builder->getPreferChannels()),
            'strategy=' . $builder->getMatchStrategy(),
        ]);
    }
}
