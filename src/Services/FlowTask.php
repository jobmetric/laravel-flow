<?php

namespace JobMetric\Flow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use JobMetric\Flow\Contracts\TaskContract;
use JobMetric\Flow\Events\FlowTask\FlowTaskDeleteEvent;
use JobMetric\Flow\Events\FlowTask\FlowTaskStoreEvent;
use JobMetric\Flow\Events\FlowTask\FlowTaskUpdateEvent;
use JobMetric\Flow\Http\Requests\FlowTask\StoreFlowTaskRequest;
use JobMetric\Flow\Http\Requests\FlowTask\UpdateFlowTaskRequest;
use JobMetric\Flow\Http\Resources\FlowTaskResource;
use JobMetric\Flow\Models\FlowTask as FlowTaskModel;
use JobMetric\PackageCore\Services\AbstractCrudService;
use Str;
use Throwable;

class FlowTask extends AbstractCrudService
{
    use InvalidatesFlowCache;

    /**
     * Human-readable entity name key used in response messages.
     *
     * @var string
     */
    protected string $entityName = 'workflow::base.entity_names.flow_task';

    /**
     * Bound model/resource classes for the base CRUD.
     *
     * @var class-string
     */
    protected static string $modelClass = FlowTaskModel::class;
    protected static string $resourceClass = FlowTaskResource::class;

    /**
     * Allowed fields for selection/filter/sort in QueryBuilder.
     *
     * @var string[]
     */
    protected static array $fields = [
        'id',
        'flow_transition_id',
        'driver',
        'config',
        'ordering',
        'status',
        'created_at',
        'updated_at',
    ];

    /**
     * Domain events mapping for CRUD lifecycle.
     *
     * @var class-string|null
     */
    protected static ?string $storeEventClass = FlowTaskStoreEvent::class;
    protected static ?string $updateEventClass = FlowTaskUpdateEvent::class;
    protected static ?string $deleteEventClass = FlowTaskDeleteEvent::class;

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
        $data = dto($data, StoreFlowTaskRequest::class, [
            'flow_id' => $data['flow_id'] ?? null,
        ]);
    }

    /**
     * Validate & normalize payload before update.
     *
     * Role: aligns input with update rules for the specific FlowTask.
     *
     * @param Model $model
     * @param array<string,mixed> $data
     *
     * @return void
     * @throws Throwable
     */
    protected function changeFieldUpdate(Model $model, array &$data): void
    {
        /** @var FlowTaskModel $task */
        $task = $model;

        $data = dto($data, UpdateFlowTaskRequest::class, [
            'flow_id' => $task->flow_id,
            'flow_task_id' => $task->id,
        ]);
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

    /**
     * get all flow task drivers
     *
     * @param string $flow_driver
     *
     * @return array
     */
    public function drivers(string $flow_driver = ''): array
    {
        $output = [];

        $global_tasks = [];
        $results = $this->resolveClassesFromDirectory('App\\Flows\\Global');
        foreach ($results as $result) {
            $global_tasks[] = $this->getDetailsFromTask($result, false);
        }
        $output[] = [
            'key' => 'Global',
            'title' => __('flow::base.flow_task.global'),
            'tasks' => $global_tasks
        ];

        if ($flow_driver != '') {
            $flow_driver = Str::studly($flow_driver);
            $driver = flowResolve($flow_driver);

            $driver_tasks = [];
            $results = $this->resolveClassesFromDirectory('App\\Flows\\Drivers\\' . $flow_driver . '\\Tasks');
            foreach ($results as $result) {
                $driver_tasks[] = $this->getDetailsFromTask($result, false);
            }

            $output[] = [
                'key' => $flow_driver,
                'title' => $driver->getTitle(),
                'tasks' => $driver_tasks
            ];
        }

        return $output;
    }

    /**
     * get details flow task drivers
     *
     * @param string $flow_driver
     * @param string $task_driver
     *
     * @return array
     */
    public function details(string $flow_driver, string $task_driver): array
    {
        $flow_driver = Str::studly($flow_driver);
        $task_driver = Str::studly($task_driver);

        if ('Global' == $flow_driver) {
            $object = resolve('\\App\\Flows\\Global\\' . $task_driver);

            return $this->getDetailsFromTask($object);
        }

        $object = resolve('\\App\\Flows\\Drivers\\' . $flow_driver . '\\Tasks\\' . $task_driver);

        return $this->getDetailsFromTask($object);
    }

    /**
     * restore flow task driver with namespace
     *
     * @param string $namespace
     *
     * @return array
     */
    private function resolveClassesFromDirectory(string $namespace): array
    {
        $files = File::files(base_path($namespace), '*.php');

        $class = [];
        foreach ($files as $file) {
            $class[] = resolve($namespace . '\\' . pathinfo($file, PATHINFO_FILENAME));
        }

        return $class;
    }

    private function getDetailsFromTask(TaskContract $task, bool $has_field = true): array
    {
        return array_merge([
            'key' => class_basename($task),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
        ], $has_field ? ['fields' => $task->getFields()] : []);
    }
}
