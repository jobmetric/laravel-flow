<?php

namespace JobMetric\Flow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Flow\Events\FlowState\FlowStateDeleteEvent;
use JobMetric\Flow\Events\FlowState\FlowStateStoreEvent;
use JobMetric\Flow\Events\FlowState\FlowStateUpdateEvent;
use JobMetric\Flow\Http\Requests\FlowState\StoreFlowStateRequest;
use JobMetric\Flow\Http\Requests\FlowState\UpdateFlowStateRequest;
use JobMetric\Flow\Http\Resources\FlowStateResource;
use JobMetric\Flow\Models\FlowState as FlowStateModel;
use JobMetric\PackageCore\Output\Response;
use JobMetric\PackageCore\Services\AbstractCrudService;
use Throwable;

/**
 * Class FlowState
 *
 * CRUD service for FlowState. Uses changeFieldStore/changeFieldUpdate with dto()
 * just like Flow service. Business rules remain minimal and localized.
 */
class FlowState extends AbstractCrudService
{
    use InvalidatesFlowCache;

    /**
     * Human-readable entity name key used in response messages.
     *
     * @var string
     */
    protected string $entityName = 'workflow::base.entity_names.flow_state';

    /**
     * Bound model/resource classes for the base CRUD.
     *
     * @var class-string
     */
    protected static string $modelClass = FlowStateModel::class;
    protected static string $resourceClass = FlowStateResource::class;

    /**
     * Allowed fields for selection/filter/sort in QueryBuilder.
     *
     * @var string[]
     */
    protected static array $fields = [
        'id',
        'flow_id',
        'type',
        'config',
        'status',
        'created_at',
        'updated_at',
    ];

    /**
     * Domain events mapping for CRUD lifecycle.
     *
     * @var class-string|null
     */
    protected static ?string $storeEventClass = FlowStateStoreEvent::class;
    protected static ?string $updateEventClass = FlowStateUpdateEvent::class;
    protected static ?string $deleteEventClass = FlowStateDeleteEvent::class;

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
        $data = dto($data, StoreFlowStateRequest::class, [
            'flow_id' => $data['flow_id'] ?? null,
        ]);

        // Force STATE type on store (type is not user-provided).
        $data['type'] = FlowStateTypeEnum::STATE();

        $isTerminal = (bool)($data['is_terminal'] ?? false);
        unset($data['is_terminal']);

        $defaults = [
            'is_terminal' => false,
            'color' => config('workflow.state.middle.color'),
            'icon' => config('workflow.state.middle.icon'),
            'position' => [
                'x' => config('workflow.state.middle.position.x'),
                'y' => config('workflow.state.middle.position.y'),
            ],
        ];

        $config = is_array($data['config'] ?? null) ? $data['config'] : [];

        Arr::set($config, 'is_terminal', $isTerminal);
        Arr::set($config, 'color', $data['color'] ?? ($config['color'] ?? $defaults['color']));
        Arr::set($config, 'icon', $data['icon'] ?? ($config['icon'] ?? $defaults['icon']));
        Arr::set($config, 'position.x', data_get($data, 'position.x', data_get($config, 'position.x', $defaults['position']['x'])));
        Arr::set($config, 'position.y', data_get($data, 'position.y', data_get($config, 'position.y', $defaults['position']['y'])));

        $data['config'] = array_replace_recursive($defaults, $config);
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
        /** @var FlowStateModel $state */
        $state = $model;

        $data = dto($data, UpdateFlowStateRequest::class, [
            'flow_id' => $state->flow_id,
            'state_id' => $state->id,
        ]);

        $defaults = [
            'is_terminal' => false,
            'color' => config('workflow.state.middle.color'),
            'icon' => config('workflow.state.middle.icon'),
            'position' => [
                'x' => config('workflow.state.middle.position.x'),
                'y' => config('workflow.state.middle.position.y'),
            ],
        ];

        if (array_key_exists('is_terminal', $data)) {
            $existing = is_object($state->config ?? null) ? (array)$state->config : [];
            $incoming = is_array($data['config'] ?? null) ? $data['config'] : [];

            $config = array_replace_recursive($existing, $incoming);

            Arr::set($config, 'is_terminal', (bool)$data['is_terminal']);
            unset($data['is_terminal']);

            $data['config'] = array_replace_recursive($defaults, $config);
        } elseif (array_key_exists('config', $data) && is_array($data['config'])) {
            $config = array_replace_recursive($state->config ?? [], $data['config']);
            if (!array_key_exists('is_terminal', $config)) {
                $config['is_terminal'] = false;
            }
            $data['config'] = array_replace_recursive($defaults, $config);
        }
    }

    /**
     * Delete a state after enforcing invariants.
     *
     * @param int $id
     * @param array<int,string> $with
     *
     * @return Response
     * @throws Throwable
     */
    public function doDestroy(int $id, array $with = []): Response
    {
        /** @var FlowStateModel $state */
        $state = FlowStateModel::query()->findOrFail($id);

        if ($state->is_start) {
            throw ValidationException::withMessages([
                'id' => [trans('workflow::base.validation.flow_state.cannot_delete_start')],
            ]);
        }

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
