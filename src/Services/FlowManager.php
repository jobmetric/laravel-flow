<?php

namespace JobMetric\Flow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JobMetric\Flow\Contracts\DriverContract;
use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Flow\Events\Flow\FlowDeleteEvent;
use JobMetric\Flow\Events\Flow\FlowForceDeleteEvent;
use JobMetric\Flow\Events\Flow\FlowRestoreEvent;
use JobMetric\Flow\Events\Flow\FlowStoreEvent;
use JobMetric\Flow\Events\Flow\FlowUpdateEvent;
use JobMetric\Flow\Http\Resources\FlowResource;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowState;
use JobMetric\PackageCore\Output\Response;
use JobMetric\PackageCore\Services\AbstractCrudService;
use Throwable;

/**
 * Class FlowManager
 *
 * Flow CRUD service built on top of AbstractCrudService.
 * - Uses Laravel API Resources (FlowResource) for output
 * - Handles START state creation on store
 * - Fires domain events on store/update/delete/restore/forceDelete
 * - Clears related caches after mutations
 * - Adds a toggleStatus() helper
 * - Provides driver resolution helpers
 */
class FlowManager extends AbstractCrudService
{
    /**
     * Enable soft-deletes + restore/forceDelete APIs.
     *
     * @var bool
     */
    protected bool $softDelete = true;

    /**
     * Human-readable entity name used in response messages.
     *
     * @var string
     */
    protected string $entityName = 'workflow::base.entity_names.flow';

    /**
     * Bind model/resource classes for the base CRUD.
     *
     * @var class-string
     */
    protected static string $modelClass = Flow::class;
    protected static string $resourceClass = FlowResource::class;

    /**
     * Allowed fields for selection/filter/sort in QueryBuilder.
     *
     * @var string[]
     */
    protected static array $fields = [
        'id',
        'subject_type',
        'subject_scope',
        'subject_collection',
        'version',
        'is_default',
        'status',
        'active_from',
        'active_to',
        'channel',
        'ordering',
        'rollout_pct',
        'environment',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected static ?string $storeEventClass = FlowStoreEvent::class;
    protected static ?string $updateEventClass = FlowUpdateEvent::class;
    protected static ?string $deleteEventClass = FlowDeleteEvent::class;
    protected static ?string $restoreEventClass = FlowRestoreEvent::class;
    protected static ?string $forceDeleteEventClass = FlowForceDeleteEvent::class;

    /**
     * Runs right after model is persisted (create).
     *
     * @param Model $model
     * @param array $data
     *
     * @return void
     */
    protected function afterStore(Model $model, array &$data): void
    {
        /** @var Flow $flow */
        $flow = $model;

        // Ensure one START node exists.
        $flow->states()->create([
            'type' => FlowStateTypeEnum::START(),
            'config' => [
                'color' => '#ffffff',
                'position' => ['x' => 0, 'y' => 0],
            ],
            // If your DB enforces status for START via CHECK, set it here or validate earlier.
            // 'status' => $data['start_status'] ?? 'start',
        ]);

        $this->forgetCache();
    }

    /**
     * Runs right after model is persisted (update).
     *
     * @param Model $model
     * @param array $data
     *
     * @return void
     */
    protected function afterUpdate(Model $model, array &$data): void
    {
        $this->forgetCache();
    }

    /**
     * Runs right after deletion.
     *
     * @param Model $model
     *
     * @return void
     */
    protected function afterDestroy(Model $model): void
    {
        $this->forgetCache();
    }

    /**
     * Runs right after restore.
     *
     * @param Model $model
     *
     * @return void
     */
    protected function afterRestore(Model $model): void
    {
        $this->forgetCache();
    }

    /**
     * Runs just before force delete.
     *
     * @param Model $model
     *
     * @return void
     */
    protected function afterForceDelete(Model $model): void
    {
        $this->forgetCache();
    }

    /**
     * Toggle the boolean 'status' field for a given Flow.
     *
     * @param int $flowId
     *
     * @return Response
     * @throws Throwable
     */
    public function toggleStatus(int $flowId): Response
    {
        return DB::transaction(function () use ($flowId) {
            /** @var Flow $flow */
            $flow = Flow::query()->findOrFail($flowId);

            $flow->status = !$flow->status;
            $flow->save();

            $this->forgetCache();

            return Response::make(true, trans('flow::base.messages.change_status'), FlowResource::make($flow));
        });
    }

    /**
     * Resolve and return the driver instance by studly driver name.
     *
     * @param string $driver Driver key (e.g., 'global' or custom).
     *
     * @return DriverContract
     */
    public function getDriver(string $driver): DriverContract
    {
        $driver = Str::studly($driver);

        if ($driver === 'Global') {
            /** @var DriverContract $instance */
            $instance = resolve("\\JobMetric\\Flow\\Flows\\Global\\GlobalDriverFlow");
        } else {
            /** @var DriverContract $instance */
            $instance = resolve("\\App\\Flows\\Drivers\\$driver\\{$driver}DriverFlow");
        }

        return $instance;
    }

    /**
     * Ask a driver for its status payload.
     *
     * @param string $driver Driver key.
     *
     * @return array
     */
    public function getStatus(string $driver): array
    {
        return $this->getDriver($driver)->getStatus();
    }

    /**
     * Get the START state of a Flow.
     *
     * @param int $flowId
     *
     * @return FlowState|null
     */
    public function getStartState(int $flowId): ?FlowState
    {
        /** @var FlowState|null $flowState */
        $flowState = FlowState::query()->where([
            'flow_id' => $flowId,
            'type' => FlowStateTypeEnum::START(),
        ])->first();

        return $flowState;
    }

    /**
     * Forget caches related to flow picking/registry (called after mutations).
     *
     * @return void
     */
    protected function forgetCache(): void
    {
        cache()->forget('flows');
        cache()->forget('flow.pick');
    }
}
