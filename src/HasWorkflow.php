<?php

namespace JobMetric\Flow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowUse;
use JobMetric\Flow\Support\FlowPicker;
use JobMetric\Flow\Support\FlowPickerBuilder;

/**
 * Trait HasWorkflow
 *
 * Provides a one-to-one polymorphic binding from the model to a selected Flow
 * via the `flow_uses` table. The trait:
 *  - Builds a per-model FlowPickerBuilder (overridable) to declare constraints.
 *  - Picks a Flow on "creating" and stores its id temporarily.
 *  - Persists the FlowUse row on "created".
 *  - Exposes relations: flowUse() and flow() for convenience.
 *
 * Models can override buildFlowPicker() to add model-specific constraints,
 * rollout rules, channels, environments, scopes, and more.
 */
trait HasWorkflow
{
    /**
     * Temporarily stores the selected flow id computed during creating event.
     *
     * @var int|null
     */
    protected ?int $selectedFlowIdForBinding = null;

    /**
     * Polymorphic one-to-one relation to FlowUse binding row.
     *
     * @return MorphOne<FlowUse>
     */
    public function flowUse(): MorphOne
    {
        return $this->morphOne(FlowUse::class, 'flowable');
    }

    /**
     * Convenient accessor to the bound Flow through FlowUse.
     *
     * @return BelongsTo<Flow, FlowUse>
     */
    public function flow(): BelongsTo
    {
        /** @var FlowUse $relation */
        $relation = $this->flowUse()->getRelated();

        return $relation->belongsTo(Flow::class, 'flow_id');
    }

    /**
     * Builds a FlowPickerBuilder for this model. Override in the model to add custom logic.
     *
     * @param FlowPickerBuilder $builder The builder to configure.
     * @return void
     *
     * @example
     * $builder->subjectType(static::class)
     *         ->onlyActive(true)
     *         ->evaluateRollout(true)
     *         ->orderByDefault();
     */
    protected function buildFlowPicker(FlowPickerBuilder $builder): void
    {
        $builder
            ->subjectType(static::class)
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
     * Picks a Flow based on the builder configuration.
     *
     * @return Flow|null
     */
    public function pickFlow(): ?Flow
    {
        $builder = new FlowPickerBuilder();
        $this->buildFlowPicker($builder);

        return (new FlowPicker())->pick($this, $builder);
    }

    /**
     * Boots the HasFlow trait lifecycle callbacks.
     *
     * @return void
     */
    public static function bootHasFlow(): void
    {
        static::creating(function (Model $model): void {
            if (method_exists($model, 'flowUse') && $model->flowUse()->exists()) {
                return;
            }

            if (property_exists($model, 'selectedFlowIdForBinding') === false) {
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
