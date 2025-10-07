<?php

namespace JobMetric\Flow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use JobMetric\Flow\Events\FlowTransition\FlowTransitionDeleteEvent;
use JobMetric\Flow\Events\FlowTransition\FlowTransitionStoreEvent;
use JobMetric\Flow\Events\FlowTransition\FlowTransitionUpdateEvent;
use JobMetric\Flow\Http\Requests\FlowTransition\StoreFlowTransitionRequest;
use JobMetric\Flow\Http\Requests\FlowTransition\UpdateFlowTransitionRequest;
use JobMetric\Flow\Http\Resources\FlowTransitionResource;
use JobMetric\Flow\Models\FlowTransition as FlowTransitionModel;
use JobMetric\PackageCore\Output\Response;
use JobMetric\PackageCore\Services\AbstractCrudService;
use Throwable;

class FlowTransition extends AbstractCrudService
{
    use InvalidatesFlowCache;

    /**
     * Human-readable entity name key used in response messages.
     *
     * @var string
     */
    protected string $entityName = 'workflow::base.entity_names.flow_transition';

    /**
     * Bound model/resource classes for the base CRUD.
     *
     * @var class-string
     */
    protected static string $modelClass = FlowTransitionModel::class;
    protected static string $resourceClass = FlowTransitionResource::class;

    /**
     * Allowed fields for selection/filter/sort in QueryBuilder.
     *
     * @var string[]
     */
    protected static array $fields = [
        'id',
        'flow_id',
        'from',
        'to',
        'slug',
        'created_at',
        'updated_at',
    ];

    /**
     * Domain events mapping for CRUD lifecycle.
     *
     * @var class-string|null
     */
    protected static ?string $storeEventClass = FlowTransitionStoreEvent::class;
    protected static ?string $updateEventClass = FlowTransitionUpdateEvent::class;
    protected static ?string $deleteEventClass = FlowTransitionDeleteEvent::class;

    /**
     * Validate & normalize payload before create.
     *
     * Role: ensures a clean, validated input for store().
     *
     * @param array<string,mixed> $data
     *
     * @return void
     * @throws Throwable
     */
    protected function changeFieldStore(array &$data): void
    {
        $data = dto($data, StoreFlowTransitionRequest::class, [
            'flow_id' => $data['flow_id'] ?? null,
        ]);
    }

    /**
     * Validate & normalize payload before update.
     *
     * Role: aligns input with update rules for the specific FlowState.
     *
     * @param Model $model
     * @param array<string,mixed> $data
     *
     * @return void
     * @throws Throwable
     */
    protected function changeFieldUpdate(Model $model, array &$data): void
    {
        /** @var FlowTransitionModel $transition */
        $transition = $model;

        $data = dto($data, UpdateFlowTransitionRequest::class, [
            'flow_transition_id' => $transition->id,
            'flow_id' => $model->flow_id,
        ]);
    }

    /**
     * Delete a transition after enforcing invariants.
     *
     * @param int $id
     * @param array $with
     *
     * @return Response
     * @throws Throwable
     */
    public function doDestroy(int $id, array $with = []): Response
    {
        // Here we need to check that the transition that comes out of the start is removed last.
        return DB::transaction(function () use ($id, $with) {
            /** @var FlowTransitionModel $transition */
            $transition = $this->getModelQuery()->with($with)->findOrFail($id);

            $startState = $transition->flow->states()->where('type', 'start')->first();
            if ($startState && $startState->id === $transition->from) {
                $lastTransition = $transition->flow->transitions()
                    ->where('id', '!=', $transition->id)
                    ->where('from', $startState->id)
                    ->exists();
                if ($lastTransition) {
                    throw new \RuntimeException(trans('workflow::errors.flow_transition.start_state_last_transition_delete'));
                }
            }
            return (new Pipeline(app()))->send($id)->through($this->getDestroyPipes())->then(fn($id) => $this->destroyModel($id, $with));
        });

        return parent::destroy($id, $with);
    }

    /**
     * Hook after store: invalidate caches.
     *
     * @param Model $model
     * @param array<string,mixed> $data
     *
     * @return void
     */
    protected function afterStore(Model $model, array &$data): void
    {
        $this->forgetCache();
    }

    /**
     * Hook after update: invalidate caches.
     *
     * @param Model $model
     * @param array<string,mixed> $data
     * @return void
     */
    protected function afterUpdate(Model $model, array &$data): void
    {
        $this->forgetCache();
    }

    /**
     * Hook after destroy: invalidate caches.
     *
     * @param Model $model
     * @return void
     */
    protected function afterDestroy(Model $model): void
    {
        $this->forgetCache();
    }
}
