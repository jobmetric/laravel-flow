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
 * Applies filters from FlowPickerBuilder and returns either a Flow or candidates collection.
 */
class FlowPicker
{
    /**
     * Simple per-request cache store.
     *
     * @var array<string,mixed>
     */
    protected static array $requestCache = [];

    /**
     * Returns a single Flow according to the builder's strategy, possibly using fallback cascade.
     *
     * @param Model $model The flowable model instance being bound.
     * @param FlowPickerBuilder $builder The configured builder.
     * @return Flow|null
     */
    public function pick(Model $model, FlowPickerBuilder $builder): ?Flow
    {
        if ($flow = $this->resolveForcedFlow($model, $builder)) {
            return $flow;
        }

        if ($builder->shouldCacheInRequest()) {
            $cacheKey = $this->computeCacheKey($model, $builder);
            if ($cacheKey !== null && array_key_exists($cacheKey, self::$requestCache)) {
                return self::$requestCache[$cacheKey] instanceof Flow
                    ? self::$requestCache[$cacheKey]
                    : (self::$requestCache[$cacheKey][0] ?? null);
            }
        }

        $candidates = $this->candidates($model, $builder);
        $picked = $candidates->first();

        if (!$picked && $builder->getFallbackCascade()) {
            $picked = $this->applyFallbackCascade($model, $builder);
        }

        if ($builder->shouldCacheInRequest()) {
            $cacheKey = $this->computeCacheKey($model, $builder);
            if ($cacheKey !== null) {
                self::$requestCache[$cacheKey] = $picked;
            }
        }

        return $picked;
    }

    /**
     * Return candidates according to current builder (without fallback).
     *
     * @param Model $model
     * @param FlowPickerBuilder $builder
     * @return Collection<int,Flow>
     */
    public function candidates(Model $model, FlowPickerBuilder $builder): Collection
    {
        $flowTable = Config::get('workflow.tables.flow', 'flows');

        $q = Flow::query();

        // Subject filters
        if ($builder->getSubjectType() !== null) {
            $q->where("{$flowTable}.subject_type", $builder->getSubjectType());
        }
        if ($builder->getSubjectScope() !== null) {
            $q->where("{$flowTable}.subject_scope", $builder->getSubjectScope());
        }

        // Environment/channel optional filters
        if ($builder->getEnvironment() !== null) {
            $q->where("{$flowTable}.environment", $builder->getEnvironment());
        }
        if ($builder->getChannel() !== null) {
            $q->where("{$flowTable}.channel", $builder->getChannel());
        }

        // Include / exclude specific flow ids
        $includeIds = $builder->getIncludeFlowIds();
        if (!empty($includeIds)) {
            $q->whereIn("{$flowTable}.id", $includeIds);
        }
        $excludeIds = $builder->getExcludeFlowIds();
        if (!empty($excludeIds)) {
            $q->whereNotIn("{$flowTable}.id", $excludeIds);
        }

        // Active/time window
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

        // Require is_default
        if ($builder->isRequireDefault()) {
            $q->where("{$flowTable}.is_default", true);
        }

        // Version constraints
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

        // Rollout filter
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
                $q->whereNull("{$flowTable}.rollout_pct");
            }
        }

        // Custom per-model filters
        foreach ($builder->getWhereCallbacks() as $callback) {
            $callback($q, $model);
        }

        // Prefer Flow IDs
        $this->applyPreferIdsOrdering($q, $flowTable, $builder->getPreferFlowIds());
        // Prefer environments
        $this->applyPreferStringOrdering($q, $flowTable, 'environment', $builder->getPreferEnvironments());
        // Prefer channels
        $this->applyPreferStringOrdering($q, $flowTable, 'channel', $builder->getPreferChannels());

        // Ordering / strategy
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
     * Applies fallback cascade steps progressively until a Flow is found or steps are exhausted.
     *
     * @param Model $model
     * @param FlowPickerBuilder $original
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
     * Resolve a forced flow id from the model, validating active/time constraints when enabled.
     *
     * @param Model $model
     * @param FlowPickerBuilder $builder
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
     * Apply CASE-based ordering boost for a list of preferred IDs.
     *
     * @param Builder $q
     * @param string $table
     * @param int[] $ids
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
     * Apply CASE-based ordering boost for string column preferences.
     *
     * @param Builder $q
     * @param string $table
     * @param string $column
     * @param string[] $values
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
     * Computes a stable integer bucket 0..99 for rollout from components.
     *
     * @param string|null $namespace
     * @param string|null $salt
     * @param string $key
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
     * Builds a cache key for per-request memoization.
     * Returns null if non-cacheable due to dynamic closures.
     *
     * @param Model $model
     * @param FlowPickerBuilder $b
     * @return string|null
     */
    protected function computeCacheKey(Model $model, FlowPickerBuilder $b): ?string
    {
        if ($b->getWhereCallbacks() || $b->getOrderingCallback()) {
            return null;
        }

        $resolver = $b->getRolloutKeyResolver();
        $rolloutKey = $resolver ? (string)($resolver($model) ?? '') : '';

        return implode('|', [
            'class=' . get_class($model),
            'id=' . (string)$model->getKey(),
            'stype=' . ($b->getSubjectType() ?? ''),
            'sscope=' . ($b->getSubjectScope() ?? ''),
            'env=' . ($b->getEnvironment() ?? ''),
            'chan=' . ($b->getChannel() ?? ''),
            'only=' . ($b->isOnlyActive() ? '1' : '0'),
            'ignTW=' . ($b->shouldIgnoreTimeWindow() ? '1' : '0'),
            'evalRO=' . ($b->shouldEvaluateRollout() ? '1' : '0'),
            'rNs=' . ($b->getRolloutNamespace() ?? ''),
            'rSalt=' . ($b->getRolloutSalt() ?? ''),
            'rKey=' . $rolloutKey,
            'reqDef=' . ($b->isRequireDefault() ? '1' : '0'),
            'vEq=' . (string)($b->getVersionEquals() ?? ''),
            'vMin=' . (string)($b->getVersionMin() ?? ''),
            'vMax=' . (string)($b->getVersionMax() ?? ''),
            'inc=' . implode(',', $b->getIncludeFlowIds()),
            'exc=' . implode(',', $b->getExcludeFlowIds()),
            'prefIds=' . implode(',', $b->getPreferFlowIds()),
            'prefEnv=' . implode(',', $b->getPreferEnvironments()),
            'prefCh=' . implode(',', $b->getPreferChannels()),
            'strategy=' . $b->getMatchStrategy(),
        ]);
    }
}
