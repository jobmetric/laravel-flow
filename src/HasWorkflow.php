<?php

namespace JobMetric\Flow;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowUse;
use JobMetric\Flow\Support\FlowPicker;
use JobMetric\Flow\Support\FlowPickerBuilder;

/**
 * Trait HasWorkflow
 *
 * Binds a model instance to a single Flow via the flow_uses table.
 * Responsibilities:
 * - Build a per-model FlowPickerBuilder (overridable via buildFlowPicker()).
 * - Pick a Flow during "creating" and cache its id temporarily.
 * - Persist the FlowUse row on "created".
 * - Provide helpers to (re)bind, unbind, and eager-load the bound Flow.
 *
 * Usage:
 *   class Order extends Model {
 *       use HasWorkflow;
 *
 *       protected function buildFlowPicker(FlowPickerBuilder $builder): void
 *       {
 *           parent::buildFlowPicker($builder);
 *           // Customize here (subject scope, channel, environment, rollout key, etc.)
 *       }
 *   }
 */
trait HasWorkflow
{
    /**
     * Cached flow id selected at "creating" time.
     *
     * @var int|null
     */
    protected ?int $selectedFlowIdForBinding = null;

    /**
     * Polymorphic binding row.
     *
     * @return MorphOne<FlowUse>
     */
    public function flowUse(): MorphOne
    {
        return $this->morphOne(FlowUse::class, 'flowable');
    }

    /**
     * Convenience accessor to the bound Flow (not an Eloquent relation).
     * Prefer eager-loading via with(['flowUse.flow']) for performance.
     *
     * @return Flow|null
     */
    public function boundFlow(): ?Flow
    {
        $use = $this->relationLoaded('flowUse')
            ? $this->getRelation('flowUse')
            : $this->flowUse()->first();

        return $use?->flow()->first();
    }

    /**
     * Configure a FlowPickerBuilder for this model. Override to customize constraints.
     *
     * @param FlowPickerBuilder $builder
     * @return void
     */
    protected function buildFlowPicker(FlowPickerBuilder $builder): void
    {
        $builder
            ->subjectType(static::class)
            ->subjectCollection($this->flowSubjectCollection())
            ->onlyActive(true)
            ->timeNow(Carbon::now('UTC'))
            ->orderByDefault()
            ->evaluateRollout(true)
            ->rolloutKeyResolver(function (Model $model): ?string {
                $key = $model->getKey();
                return $key === null ? null : (string)$key;
            });
    }

    /**
     * Resolve subject_collection value for this model (override in your model if needed).
     *
     * Default implementation tries to use a "collection" attribute if present.
     *
     * @return string|null
     */
    protected function flowSubjectCollection(): ?string
    {
        // If your model stores the logical collection/type in another attribute,
        // override this method and return that value.
        $val = $this->getAttribute('collection');

        return $val === null ? null : (string)$val;
    }

    /**
     * Create and return a configured builder for advanced scenarios.
     *
     * @return FlowPickerBuilder
     */
    protected function makeFlowPicker(): FlowPickerBuilder
    {
        $builder = new FlowPickerBuilder();
        $this->buildFlowPicker($builder);

        return $builder;
    }

    /**
     * Pick a Flow based on the current builder configuration.
     *
     * @return Flow|null
     */
    public function pickFlow(): ?Flow
    {
        return (new FlowPicker())->pick($this, $this->makeFlowPicker());
    }

    /**
     * Bind the given Flow to this model (create/update the FlowUse row).
     *
     * @param Flow $flow
     * @param Carbon|null $usedAt
     *
     * @return Model|MorphOne|FlowUse
     */
    public function bindFlow(Flow $flow, ?Carbon $usedAt = null): Model|MorphOne|FlowUse
    {
        $usedAt = $usedAt ?? Carbon::now('UTC');

        if ($this->relationLoaded('flowUse') && $this->getRelation('flowUse')) {
            /** @var FlowUse $use */
            $use = $this->getRelation('flowUse');
            $use->fill(['flow_id' => $flow->getKey(), 'used_at' => $usedAt])->save();

            return $use;
        }

        $existing = $this->flowUse()->first();
        if ($existing) {
            $existing->fill(['flow_id' => $flow->getKey(), 'used_at' => $usedAt])->save();

            return $existing;
        }

        return $this->flowUse()->create([
            'flow_id' => $flow->getKey(),
            'used_at' => $usedAt,
        ]);
    }

    /**
     * Re-pick using the builder and rebind to the chosen Flow if any.
     *
     * @param callable(FlowPickerBuilder):void|null $tuner Optional builder mutator.
     * @return Flow|null
     */
    public function rebindFlow(?callable $tuner = null): ?Flow
    {
        $builder = $this->makeFlowPicker();
        if ($tuner) {
            $tuner($builder);
        }

        $flow = (new FlowPicker())->pick($this, $builder);
        if ($flow) {
            $this->bindFlow($flow);
        }

        return $flow;
    }

    /**
     * Remove the binding row if present.
     *
     * @return void
     */
    public function unbindFlow(): void
    {
        $this->flowUse()->delete();
        $this->selectedFlowIdForBinding = null;
    }

    /**
     * Local scope to eager-load the bound Flow efficiently.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithFlow(Builder $query): Builder
    {
        return $query->with(['flowUse.flow']);
    }

    /**
     * Boot the HasWorkflow trait lifecycle hooks.
     *
     * @return void
     */
    public static function bootHasWorkflow(): void
    {
        static::creating(function (Model $model): void {
            // Skip if already bound (defensive).
            if (method_exists($model, 'flowUse') && $model->flowUse()->exists()) {
                return;
            }

            if (!property_exists($model, 'selectedFlowIdForBinding')) {
                $model->selectedFlowIdForBinding = null;
            }

            /** @var self $model */
            $flow = $model->pickFlow();
            $model->selectedFlowIdForBinding = $flow?->getKey();
        });

        static::created(function (Model $model): void {
            /** @var self $model */
            if ($model->flowUse()->exists()) {
                return;
            }

            $flowId = $model->selectedFlowIdForBinding;

            // If nothing was selected at "creating", try again (id is now available).
            if ($flowId === null) {
                $flow = $model->pickFlow();
                $flowId = $flow?->getKey();
            }

            if ($flowId === null) {
                return;
            }

            $model->flowUse()->create([
                'flow_id' => $flowId,
                'used_at' => Carbon::now('UTC'),
            ]);
        });
    }
}
