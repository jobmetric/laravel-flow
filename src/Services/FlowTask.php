<?php

namespace JobMetric\Flow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JobMetric\Flow\Contracts\AbstractTaskDriver;
use JobMetric\Flow\Events\FlowTask\FlowTaskDeleteEvent;
use JobMetric\Flow\Events\FlowTask\FlowTaskStoreEvent;
use JobMetric\Flow\Events\FlowTask\FlowTaskUpdateEvent;
use JobMetric\Flow\Http\Requests\FlowTask\StoreFlowTaskRequest;
use JobMetric\Flow\Http\Requests\FlowTask\UpdateFlowTaskRequest;
use JobMetric\Flow\Http\Resources\FlowTaskResource;
use JobMetric\Flow\Models\FlowTask as FlowTaskModel;
use JobMetric\Flow\Support\FlowTaskRegistry;
use JobMetric\Form\Support\IOForm;
use JobMetric\PackageCore\Services\AbstractCrudService;
use Throwable;

class FlowTask extends AbstractCrudService
{
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
     * Uses FormBuilder from the task driver to validate and normalize config data via IOForm.
     *
     * @param array<string,mixed> $data
     *
     * @return void
     * @throws Throwable
     */
    protected function changeFieldStore(array &$data): void
    {
        // First validate using StoreFlowTaskRequest
        $data = dto($data, StoreFlowTaskRequest::class, [
            'flow_transition_id' => $data['flow_transition_id'] ?? null,
        ]);

        // Normalize config using IOForm if driver is provided
        if (isset($data['driver']) && isset($data['config'])) {
            $driver = $this->resolveDriver($data['driver']);

            if (! is_null($driver)) {
                $data['config'] = IOForm::forStore($driver->form(), $data['config']);
            }
        }
    }

    /**
     * Validate & normalize payload before update.
     *
     * Role: aligns input with update rules for the specific FlowTask.
     * Uses FormBuilder from the task driver to validate and normalize config data via IOForm.
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
            'flow_id'      => $task->transition->flow_id,
            'flow_task_id' => $task->id,
        ]);

        // Determine driver class: use updated value if provided, otherwise use existing
        $driverClass = $data['driver'] ?? null;
        if (is_null($driverClass) && ! is_null($task->driver)) {
            $driverClass = is_object($task->driver) ? get_class($task->driver) : $task->driver;
        }

        // Normalize config using IOForm if driver is available
        if (! is_null($driverClass) && isset($data['config'])) {
            $driver = $this->resolveDriver($driverClass);

            if (! is_null($driver)) {
                $data['config'] = IOForm::forStore($driver->form(), $data['config']);
            }
        }
    }

    /**
     * Resolve a task driver instance from the registry by class name.
     *
     * @param string $driverClass
     *
     * @return AbstractTaskDriver|null
     */
    public function resolveDriver(string $driverClass): ?AbstractTaskDriver
    {
        /** @var FlowTaskRegistry $registry */
        $registry = app('FlowTaskRegistry');

        return $registry->get($driverClass);
    }

    /**
     * Common hook executed after all mutation operations.
     * Invalidates flow-related caches.
     *
     * @param string $operation The operation being performed: 'store'|'update'|'destroy'|'restore'|'forceDelete'
     * @param Model $model      The model instance
     * @param array $data       The data payload (empty for destroy/restore/forceDelete)
     *
     * @return void
     */
    protected function afterCommon(string $operation, Model $model, array $data = []): void
    {
        forgetFlowCache();
    }

    /**
     * Retrieve registered flow tasks grouped by subject (model) and optionally filtered by task type(s).
     *
     * @param string $taskDriver
     * @param array|string|null $taskTypes Allowed values: action, validation, restriction
     *
     * @return array<int, array<string, mixed>>
     * @throws Throwable
     */
    public function drivers(string $taskDriver = '', array|string|null $taskTypes = null): array
    {
        /** @var FlowTaskRegistry $registry */
        $registry = app('FlowTaskRegistry');

        $tasks = collect($registry->all());

        if ($taskDriver !== '') {
            $normalized = $this->normalizeDriverKey($taskDriver);

            $tasks = $tasks->filter(function ($_, string $subject) use ($normalized) {
                return $subject === $normalized;
            });
        }

        $result = $tasks->map(function (array $types, string $subject): array {
            $taskDetails = collect($types)->flatMap(fn (array $taskGroup) => $taskGroup)->map(fn (
                AbstractTaskDriver $task
            ) => $this->taskDetails($task))->values()->all();

            return [
                'subject' => $subject,
                'title'   => class_basename($subject),
                'tasks'   => $taskDetails,
            ];
        })->values();

        if (! is_null($taskTypes)) {
            $filters = array_map(function ($type) {
                $normalized = Str::lower((string) $type);

                FlowTaskModel::assertValidType($normalized);

                return $normalized;
            }, array_values((array) $taskTypes));

            $result = $result->map(function (array $group) use ($filters): array {
                $group['tasks'] = collect($group['tasks'])->filter(fn (array $task
                ) => in_array($task['type'], $filters, true))->values()->all();

                return $group;
            })->filter(fn (array $group) => count($group['tasks']) > 0)->values();
        }

        return $result->all();
    }

    /**
     * Get detailed information about a registered task driver.
     *
     * @param string $taskDriver
     * @param string $taskClassName
     *
     * @return array<string, mixed>
     * @throws Throwable
     */
    public function details(string $taskDriver, string $taskClassName): array
    {
        /** @var FlowTaskRegistry $registry */
        $registry = app('FlowTaskRegistry');

        $subject = $taskDriver !== '' ? $this->normalizeDriverKey($taskDriver) : null;
        $taskBasename = Str::studly($taskClassName);

        $subjects = ! is_null($subject) ? [$subject => $registry->forSubject($subject)] : $registry->all();

        foreach ($subjects as $types) {
            foreach ($types as $tasks) {
                foreach ($tasks as $task) {
                    if (class_basename($task) === $taskBasename || $task::class === $taskBasename) {
                        return $this->taskDetails($task);
                    }
                }
            }
        }

        Log::warning('Workflow task driver not found in registry; skipping details response.', [
            'task'    => $taskClassName,
            'subject' => $subject,
        ]);

        return [];
    }

    /**
     * Normalize driver parameter to a subject (model) key.
     */
    protected function normalizeDriverKey(string $driver): string
    {
        $driver = trim($driver);

        if (class_exists($driver) || str_contains($driver, '\\')) {
            return $driver;
        }

        return Str::studly($driver);
    }

    /**
     * Transform a task driver into response-friendly details.
     *
     * @throws Throwable
     */
    protected function taskDetails(AbstractTaskDriver $task): array
    {
        $definition = $task::definition();

        $details = [
            'key'   => class_basename($task),
            'title' => trans($definition->title),
            'type'  => FlowTaskModel::determineTaskType($task),
            'class' => $task::class,
        ];

        if ($definition->description) {
            $details['description'] = trans($definition->description);
        }

        return $details;
    }
}
