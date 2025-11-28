<?php

namespace JobMetric\Flow\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JobMetric\Flow\Contracts\AbstractActionTask;
use JobMetric\Flow\Contracts\AbstractRestrictionTask;
use JobMetric\Flow\Contracts\AbstractTaskDriver;
use JobMetric\Flow\Contracts\AbstractValidationTask;
use JobMetric\Flow\DTO\TransitionResult;
use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Flow\Events\FlowTransition\FlowTransitionDeleteEvent;
use JobMetric\Flow\Events\FlowTransition\FlowTransitionStoreEvent;
use JobMetric\Flow\Events\FlowTransition\FlowTransitionUpdateEvent;
use JobMetric\Flow\Exceptions\TaskRestrictionException;
use JobMetric\Flow\Exceptions\TransitionNotFoundException;
use JobMetric\Flow\Http\Requests\FlowTransition\StoreFlowTransitionRequest;
use JobMetric\Flow\Http\Requests\FlowTransition\UpdateFlowTransitionRequest;
use JobMetric\Flow\Http\Resources\FlowTransitionResource;
use JobMetric\Flow\Models\FlowTask as FlowTaskModel;
use JobMetric\Flow\Models\FlowTransition as FlowTransitionModel;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskRegistry;
use JobMetric\Flow\Support\RestrictionResult;
use JobMetric\PackageCore\Output\Response;
use JobMetric\PackageCore\Services\AbstractCrudService;
use RuntimeException;
use Throwable;

class FlowTransition extends AbstractCrudService
{
    public function __construct(
        protected FlowTaskRegistry $taskRegistry
    ) {
        parent::__construct();
    }

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
            'flow_id'            => $transition->flow_id,
            'flow_transition_id' => $transition->id,
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
            $transition = (static::$modelClass)::query()->with($with)->findOrFail($id);

            $startStateId = $transition->flow->states()->where('type', FlowStateTypeEnum::START())->value('id');

            if ($startStateId && (int) $transition->from === (int) $startStateId) {
                $hasAnotherFromStart = $transition->flow->transitions()->where('id', '!=', $transition->id)->exists();

                if ($hasAnotherFromStart) {
                    throw new RuntimeException(trans('workflow::errors.flow_transition.start_state_last_transition_delete'));
                }
            }

            return parent::destroy($id, $with);
        });
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
     * Ensure the provided task driver is registered for the given flow subject and type.
     *
     * @param string $subjectType
     * @param string $taskType
     * @param FlowTaskModel $task
     * @param AbstractTaskDriver $driver
     *
     * @return bool
     * @throws Throwable
     */
    protected function taskIsRegistered(
        string $subjectType,
        string $taskType,
        FlowTaskModel $task,
        AbstractTaskDriver $driver
    ): bool {
        try {
            if ($this->taskRegistry->has($subjectType, $taskType, $driver::class)) {
                return true;
            }
        } catch (Throwable $e) {
            logs()->warning('Workflow task driver not registered in FlowTaskRegistry.', [
                'task_class'         => $driver::class,
                'subject'            => $subjectType,
                'type'               => $taskType,
                'flow_task_id'       => $task->id,
                'flow_transition_id' => $task->flow_transition_id,
            ]);
        }

        return false;
    }

    /**
     * Execute a flow transition by its key (ID or slug).
     *
     * @param int|string $key            Transition ID or slug
     * @param array $payload             Data payload for the transition
     * @param Authenticatable|null $user Optional user context
     *
     * @return TransitionResult
     * @throws Throwable
     */
    public function runner(int|string $key, array $payload = [], ?Authenticatable $user = null): TransitionResult
    {
        $transition = FlowTransitionModel::query()->when(is_string($key), function ($query) use ($key) {
            $query->where('slug', $key);
        })->when(is_int($key), function ($query) use ($key) {
            $query->where('id', $key);
        })->with([
            'flow',
            'fromState',
            'toState',
            'tasks',
        ])->firstOrFail();

        if (! $transition) {
            throw new TransitionNotFoundException;
        }

        $transitionResult = new TransitionResult;
        $subjectType = (string) $transition->flow->subject_type;
        $context = new FlowTaskContext($transition->flow->subject_type, $transitionResult, $payload, $user);

        // Run restrictions tasks
        foreach ($transition->tasks as $item) {
            /** @var FlowTaskModel $item */
            $driver = $item->driver;

            if (is_null($driver)) {
                continue;
            }

            $taskType = FlowTaskModel::determineTaskType($driver);

            if ($taskType == FlowTaskModel::TYPE_RESTRICTION) {
                /** @var AbstractRestrictionTask $driver */
                if (! $this->taskIsRegistered($subjectType, $taskType, $item, $driver)) {
                    continue;
                }

                $context->replaceConfig($item->config);

                /** @var RestrictionResult $result */
                $result = $driver->restriction($context);

                if (! $result->allowed()) {
                    // Stop processing and return the restriction result
                    throw new TaskRestrictionException($result->message() ?? null, $result->code() ?? 400);
                }
            }
        }

        // Run validations tasks
        $rules = [];
        $messages = [];
        $attributes = [];
        foreach ($transition->tasks as $item) {
            /** @var FlowTaskModel $item */
            $driver = $item->driver;

            if (is_null($driver)) {
                continue;
            }

            $taskType = FlowTaskModel::determineTaskType($driver);

            if ($taskType == FlowTaskModel::TYPE_VALIDATION) {
                /** @var AbstractValidationTask $driver */
                if (! $this->taskIsRegistered($subjectType, $taskType, $item, $driver)) {
                    continue;
                }

                $context->replaceConfig($item->config);

                $rules = array_merge($rules, $driver->rules($context));
                $messages = array_merge($messages, $driver->messages($context));
                $attributes = array_merge($attributes, $driver->attributes($context));
            }
        }

        $validator = validator($payload, $rules, $messages, $attributes);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Run actions tasks
        foreach ($transition->tasks as $item) {
            /** @var FlowTaskModel $item */
            $driver = $item->driver;

            if (is_null($driver)) {
                continue;
            }

            $taskType = FlowTaskModel::determineTaskType($driver);

            if ($taskType == FlowTaskModel::TYPE_ACTION) {
                /** @var AbstractActionTask $driver */
                if (! $this->taskIsRegistered($subjectType, $taskType, $item, $driver)) {
                    continue;
                }

                $context->replaceConfig($item->config);

                $driver->run($context);
            }
        }

        return $transitionResult;
    }
}
