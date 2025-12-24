<?php

namespace JobMetric\Flow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowUse;
use JobMetric\Flow\Support\FlowPickerBuilder;
use LogicException;

/**
 * Trait HasFlow
 *
 * A simplified trait for directly binding a model to a Flow using Flow ID.
 * This trait is suitable for 90% of projects where a model has only one flow.
 *
 * Unlike HasWorkflow which uses FlowPicker with complex logic (subject_scope, subject_collection,
 * rollout_pct, active_window), this trait directly accepts Flow ID.
 *
 * This trait uses HasWorkflow internally and only overrides the flow picking logic.
 *
 * Usage:
 *   class Order extends Model {
 *       use HasFlow;
 *
 *       // Override flowId() to return a specific Flow ID
 *       protected function flowId(): ?int
 *       {
 *           return 1; // or from config, etc.
 *       }
 *   }
 */
trait HasFlow
{
    use HasWorkflow;

    /**
     * Boots the HasFlow trait lifecycle hooks.
     * Overrides HasWorkflow boot to use simplified flow resolution.
     *
     * @return void
     */
    public static function bootHasFlow(): void
    {
        static::bootHasWorkflow();
    }

    /**
     * Configures the FlowPickerBuilder to use forceFlowIdResolver for direct Flow ID/Slug resolution.
     * This overrides HasWorkflow::buildFlowPicker to use simplified flow resolution.
     *
     * @param FlowPickerBuilder $builder The builder to tune.
     *
     * @return void
     */
    protected function buildFlowPicker(FlowPickerBuilder $builder): void
    {
        // Copy the default configuration from HasWorkflow::buildFlowPicker
        $builder->subjectType(static::class)
            ->subjectCollection($this->flowSubjectCollection())
            ->onlyActive(false) // Disable active checks for HasFlow - we just want to bind by ID
            ->timeNow(Carbon::now('UTC'))
            ->orderByDefault()
            ->evaluateRollout(false) // Disable rollout for HasFlow - we just want to bind by ID
            ->rolloutKeyResolver(function (Model $model): ?string {
                $key = $model->getKey();

                return $key === null ? null : (string) $key;
            });

        // Use forceFlowIdResolver to directly resolve Flow by ID
        $builder->forceFlowIdResolver(function (Model $model): ?int {
            /** @var self $model */
            $flow = $model->resolveFlow();

            return $flow?->getKey();
        });
    }

    /**
     * Resolves the logical subject collection; override in subject models if needed.
     * This is copied from HasWorkflow to maintain compatibility.
     *
     * @return string|null
     */
    protected function flowSubjectCollection(): ?string
    {
        $val = $this->getAttribute('collection');

        return $val === null ? null : (string) $val;
    }

    /**
     * Resolve the Flow by ID.
     * Override flowId() method in your model to customize.
     *
     * @return Flow|null
     */
    protected function resolveFlow(): ?Flow
    {
        $flowId = $this->flowId();
        if ($flowId !== null) {
            return Flow::query()->find($flowId);
        }

        return null;
    }

    /**
     * Get the Flow ID for this model.
     * Override this method in your model to return a specific Flow ID.
     *
     * @return int|null
     */
    protected function flowId(): ?int
    {
        // Default: try to get from model attribute if exists
        if ($this->getAttribute('flow_id') !== null) {
            return (int) $this->getAttribute('flow_id');
        }

        return null;
    }


    /**
     * Binds the given Flow to this model by upserting the FlowUse row.
     * Overrides HasWorkflow::bindFlow() to accept Flow ID in addition to Flow instance.
     *
     * @param Flow|int $flow      The flow to bind (Flow instance or ID).
     * @param Carbon|null $usedAt Optional timestamp of binding.
     *
     * @return Model|MorphOne|FlowUse
     */
    public function bindFlow(
        Flow|int $flow,
        ?Carbon $usedAt = null
    ): Model|MorphOne|FlowUse {
        // Resolve Flow instance if ID provided
        if (is_int($flow)) {
            $flowId = $flow;
            $flow = Flow::query()->find($flowId);
            if (! $flow) {
                throw new LogicException("Flow with ID {$flowId} not found.");
            }
        }

        if (! $flow instanceof Flow) {
            throw new LogicException("Invalid flow provided. Expected Flow instance or ID.");
        }

        // Call the bindFlow logic from HasWorkflow (copied here since parent:: doesn't work with traits)
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
}
