<?php

namespace JobMetric\Flow\Services;

use BackedEnum;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
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
use JobMetric\Flow\HasWorkflow;
use JobMetric\Flow\Http\Requests\FlowTransition\StoreFlowTransitionRequest;
use JobMetric\Flow\Http\Requests\FlowTransition\UpdateFlowTransitionRequest;
use JobMetric\Flow\Http\Resources\FlowTransitionResource;
use JobMetric\Flow\Models\FlowInstance;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Flow\Models\FlowTask as FlowTaskModel;
use JobMetric\Flow\Models\FlowTransition as FlowTransitionModel;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskRegistry;
use JobMetric\Flow\Support\RestrictionResult;
use JobMetric\PackageCore\Output\Response;
use JobMetric\PackageCore\Services\AbstractCrudService;
use LogicException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Throwable;
use ValueError;

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
                    throw new RuntimeException(trans('workflow::base.errors.flow_transition.start_state_last_transition_delete'));
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
        // Get the task's declared subject
        $taskSubject = $driver::subject();

        // If task subject doesn't match flow subject, reject it
        if ($taskSubject !== '' && $taskSubject !== $subjectType) {
            return false;
        }

        // Use the same registry instance that's used in tests
        // Resolve from container to ensure we get the singleton instance
        $registry = app('FlowTaskRegistry');

        // If task class is registered in registry, allow it (subject already matched above)
        // This is the simplest and most reliable check - if class exists in registry and subject matches, allow it
        if ($registry->hasClass($driver::class)) {
            return true;
        }

        // Additional checks for specific registration
        try {
            // Check if task is registered for the specific subject and type
            if ($registry->has($subjectType, $taskType, $driver::class)) {
                return true;
            }

            // Also check with task's own subject (in case registration used task's subject)
            if ($taskSubject !== '' && $registry->has($taskSubject, $taskType, $driver::class)) {
                return true;
            }
        } catch (Throwable $e) {
            logs()->warning('Workflow task driver registration check failed.', [
                'task_class'         => $driver::class,
                'subject'            => $subjectType,
                'task_subject'       => $taskSubject,
                'type'               => $taskType,
                'flow_task_id'       => $task->id,
                'flow_transition_id' => $task->flow_transition_id,
                'error'              => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Execute a flow transition by its key (ID or slug).
     *
     * @param int|string $key            Transition ID or slug
     * @param Model|null $subject        The subject model instance (optional, will be resolved from FlowInstance if
     *                                   not provided)
     * @param array $payload             Data payload for the transition
     * @param Authenticatable|null $user Optional user context
     *
     * @return TransitionResult
     * @throws Throwable
     */
    public function runner(
        int|string $key,
        ?Model $subject = null,
        array $payload = [],
        ?Authenticatable $user = null
    ): TransitionResult {
        return DB::transaction(function () use ($key, $subject, $payload, $user) {
            // Load transition with all required relations
            $transition = FlowTransitionModel::query()->when(is_string($key), function ($query) use ($key) {
                $query->where('slug', $key);
            })->when(is_int($key), function ($query) use ($key) {
                $query->where('id', $key);
            })->with([
                'flow',
                'fromState',
                'toState',
                'tasks' => function ($query) {
                    $query->where('status', true)->orderBy('ordering');
                },
            ])->firstOrFail();

            // Resolve subject model if not provided
            if ($subject === null) {
                $subject = $this->resolveSubjectFromInstance($transition);
            }

            if ($subject === null) {
                throw new LogicException(trans('workflow::base.errors.flow_transition.subject_model_required'));
            }

            // Validate subject type matches flow subject type
            $subjectType = (string) $transition->flow->subject_type;
            if (get_class($subject) !== $subjectType) {
                throw new LogicException(trans('workflow::base.errors.flow_transition.subject_model_type_mismatch', [
                    'expected' => $subjectType,
                    'got'      => get_class($subject),
                ]));
            }

            // Initialize transition result
            $transitionResult = TransitionResult::success();
            $context = new FlowTaskContext($subject, $transitionResult, $payload, $user);

            try {
                // Step 1: Run restriction tasks
                $this->runRestrictionTasks($transition, $subjectType, $context);

                // Step 2: Run validation tasks
                $this->runValidationTasks($transition, $subjectType, $context, $payload);

                // Step 3: Run action tasks
                $this->runActionTasks($transition, $subjectType, $context);

                // Step 4: Update model status if toState has a status
                $this->updateModelStatus($subject, $transition->toState);

                // Step 5: Create or update FlowInstance
                $this->createOrUpdateInstance($transition, $subject, $user);

                // Mark transition as successful
                $transitionResult->addMessage(trans('workflow::base.messages.transition_executed_successfully'));

                return $transitionResult;
            } catch (TaskRestrictionException $e) {
                $transitionResult->markFailed('restriction_failed', $e->getCode() ?: '400')
                    ->addError($e->getMessage() ?? trans('workflow::base.errors.flow_transition.transition_restriction_failed'));

                throw $e;
            } catch (ValidationException $e) {
                $transitionResult->markFailed('validation_failed', '422')->mergeData(['errors' => $e->errors()]);

                throw $e;
            } catch (Throwable $e) {
                $transitionResult->markFailed('execution_failed', '500')
                    ->addError($e->getMessage() ?: trans('workflow::base.errors.flow_transition.transition_execution_failed'));

                // Log the error for debugging
                logs()->error('Flow transition execution failed', [
                    'transition_id'   => $transition->id,
                    'transition_slug' => $transition->slug,
                    'subject_type'    => get_class($subject),
                    'subject_id'      => $subject->getKey(),
                    'error'           => $e->getMessage(),
                    'trace'           => $e->getTraceAsString(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Resolve subject model from existing FlowInstance.
     *
     * @param FlowTransitionModel $transition
     *
     * @return Model|null
     */
    protected function resolveSubjectFromInstance(FlowTransitionModel $transition): ?Model
    {
        // Try to find an active instance for this transition
        $instance = FlowInstance::query()->where('flow_transition_id', $transition->id)->active()->latest()->first();

        if ($instance) {
            return $instance->instanceable;
        }

        return null;
    }

    /**
     * Run restriction tasks for the transition.
     *
     * @param FlowTransitionModel $transition
     * @param string $subjectType
     * @param FlowTaskContext $context
     *
     * @return void
     * @throws Throwable
     */
    protected function runRestrictionTasks(
        FlowTransitionModel $transition,
        string $subjectType,
        FlowTaskContext $context
    ): void {
        foreach ($transition->tasks as $item) {
            /** @var FlowTaskModel $item */
            $driver = $item->driver;

            if (is_null($driver)) {
                continue;
            }

            $taskType = FlowTaskModel::determineTaskType($driver);

            if ($taskType == FlowTaskModel::TYPE_RESTRICTION) {
                /** @var AbstractRestrictionTask $driver */
                // Check if task is registered - if not, skip it
                if (! $this->taskIsRegistered($subjectType, $taskType, $item, $driver)) {
                    continue;
                }

                // Update context with task config
                $context->replaceConfig($item->config->toArray() ?? []);

                // Execute restriction task
                /** @var RestrictionResult $result */
                $result = $driver->restriction($context);

                // If restriction denies, throw exception
                if (! $result->allowed()) {
                    $code = $result->code();
                    // Convert string code to int if needed (default to 400)
                    $codeInt = is_numeric($code) ? (int) $code : 400;
                    throw new TaskRestrictionException($result->message() ?? trans('workflow::base.errors.flow_transition.transition_restriction_failed'), $codeInt);
                }
            }
        }
    }

    /**
     * Run validation tasks for the transition.
     *
     * @param FlowTransitionModel $transition
     * @param string $subjectType
     * @param FlowTaskContext $context
     * @param array $payload
     *
     * @return void
     * @throws Throwable
     */
    protected function runValidationTasks(
        FlowTransitionModel $transition,
        string $subjectType,
        FlowTaskContext $context,
        array $payload
    ): void {
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
                // Check if task is registered - if not, skip it
                if (! $this->taskIsRegistered($subjectType, $taskType, $item, $driver)) {
                    continue;
                }

                // Update context with task config
                $context->replaceConfig($item->config->toArray() ?? []);

                // Collect validation rules, messages, and attributes
                $rules = array_merge($rules, $driver->rules($context));
                $messages = array_merge($messages, $driver->messages($context));
                $attributes = array_merge($attributes, $driver->attributes($context));
            }
        }

        // If we have validation rules, validate the payload
        if (! empty($rules)) {
            $validator = validator($payload, $rules, $messages, $attributes);

            if ($validator->fails()) {
                throw ValidationException::withMessages($validator->errors()->toArray());
            }
        }
    }

    /**
     * Run action tasks for the transition.
     *
     * @param FlowTransitionModel $transition
     * @param string $subjectType
     * @param FlowTaskContext $context
     *
     * @return void
     * @throws Throwable
     */
    protected function runActionTasks(
        FlowTransitionModel $transition,
        string $subjectType,
        FlowTaskContext $context
    ): void {
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

                $context->replaceConfig($item->config->toArray() ?? []);

                $driver->run($context);
            }
        }
    }

    /**
     * Update the subject model's status based on the transition's toState.
     *
     * @param Model $subject
     * @param FlowState|null $toState
     *
     * @return void
     */
    protected function updateModelStatus(Model $subject, ?FlowState $toState): void
    {
        if ($toState === null || empty($toState->status)) {
            return;
        }

        // Check if model uses HasWorkflow trait
        if (! $this->usesHasWorkflow($subject)) {
            return;
        }

        // Get status column name using reflection (method is protected)
        $statusColumn = $this->getFlowStatusColumn($subject);

        // Get the status value (handle enum casting)
        $statusValue = $toState->status;

        // If subject has enum casting, try to resolve enum value
        $enumClass = $this->getFlowStatusEnumClass($subject);
        if ($enumClass !== null) {
            // Try to find enum case by value
            if (is_subclass_of($enumClass, BackedEnum::class)) {
                try {
                    $enumValue = $enumClass::from($statusValue);
                    $subject->setAttribute($statusColumn, $enumValue);
                    $subject->save();

                    return;
                } catch (ValueError $e) {
                    // Enum value not found, use raw value
                }
            }
        }

        // Use raw status value
        $subject->setAttribute($statusColumn, $statusValue);
        $subject->save();
    }

    /**
     * Check if the model uses HasWorkflow trait.
     *
     * @param Model $subject
     *
     * @return bool
     */
    protected function usesHasWorkflow(Model $subject): bool
    {
        $traits = class_uses_recursive(get_class($subject));

        return in_array(HasWorkflow::class, $traits, true);
    }

    /**
     * Get the flow status column name from the model using reflection.
     *
     * @param Model $subject
     *
     * @return string
     */
    protected function getFlowStatusColumn(Model $subject): string
    {
        try {
            $reflection = new ReflectionClass($subject);
            $method = $reflection->getMethod('flowStatusColumn');

            return $method->invoke($subject);
        } catch (ReflectionException $e) {
            // Fallback to default
            return 'status';
        }
    }

    /**
     * Get the flow status enum class from the model.
     * flowStatusEnumClass is public, so we can call it directly.
     *
     * @param Model $subject
     *
     * @return class-string|null
     */
    protected function getFlowStatusEnumClass(Model $subject): ?string
    {
        if (! $this->usesHasWorkflow($subject)) {
            return null;
        }

        // flowStatusEnumClass is public in HasWorkflow trait
        if (method_exists($subject, 'flowStatusEnumClass')) {
            return $subject->flowStatusEnumClass();
        }

        return null;
    }

    /**
     * Create or update FlowInstance for the transition.
     *
     * @param FlowTransitionModel $transition
     * @param Model $subject
     * @param Authenticatable|null $user
     *
     * @return FlowInstance
     */
    protected function createOrUpdateInstance(
        FlowTransitionModel $transition,
        Model $subject,
        ?Authenticatable $user
    ): FlowInstance {
        $now = Carbon::now('UTC');

        // Check if there's an existing active instance for this subject
        /* @var FlowInstance|null $existingInstance */
        $existingInstance = FlowInstance::query()->forModel($subject)->active()->latest()->first();

        if ($existingInstance) {
            // Update existing instance
            $existingInstance->flow_transition_id = $transition->id;
            $existingInstance->actor_type = $user ? get_class($user) : null;
            $existingInstance->actor_id = $user?->getKey();

            // Mark as completed if transition leads to an end state
            if ($transition->toState && $transition->toState->is_end) {
                $existingInstance->completed_at = $now;
            }

            $existingInstance->save();

            return $existingInstance;
        }

        // Create new instance
        return FlowInstance::query()->create([
            'instanceable_type'  => get_class($subject),
            'instanceable_id'    => $subject->getKey(),
            'flow_transition_id' => $transition->id,
            'actor_type'         => $user ? get_class($user) : null,
            'actor_id'           => $user?->getKey(),
            'started_at'         => $now,
            'completed_at'       => ($transition->toState && $transition->toState->is_end) ? $now : null,
        ]);
    }
}
