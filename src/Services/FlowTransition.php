<?php

namespace JobMetric\Flow\Services;

use BackedEnum;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
                // Check transition type and execute accordingly
                $isSelfLoop = $transition->is_self_loop_transition;
                $isGenericInput = $transition->is_generic_input_transition;

                if ($isSelfLoop) {
                    // For self-loop transitions, only run tasks of this transition
                    $this->runRestrictionTasks($transition, $subjectType, $context);
                    $this->runValidationTasks($transition, $subjectType, $context, $payload);
                    $this->runActionTasks($transition, $subjectType, $context);
                }
                else if ($isGenericInput) {
                    // For generic input transitions (from = null), only run tasks of this transition
                    // This allows executing a transition that doesn't come from a specific state
                    // Useful when a state can only be reached via generic input transitions
                    $this->runRestrictionTasks($transition, $subjectType, $context);
                    $this->runValidationTasks($transition, $subjectType, $context, $payload);
                    $this->runActionTasks($transition, $subjectType, $context);
                }
                else {
                    // For specific transitions (from and to are set), run related transitions in order:
                    // 1. Generic output transitions (to = null) - transitions leaving from fromState
                    // 2. Specific transitions (from and to are set and not equal) - the current transition
                    // 3. Generic input transitions (from = null) - transitions entering to fromState

                    // Get the state we're transitioning from
                    $fromState = $transition->fromState;

                    if ($fromState) {
                        // Get all related transitions for this state in the correct order
                        $relatedTransitions = $this->getRelatedTransitionsForState($fromState, $transition);

                        // Execute transitions in order: generic output, specific, generic input
                        foreach ($relatedTransitions as $relatedTransition) {
                            $this->runRestrictionTasks($relatedTransition, $subjectType, $context);
                            $this->runValidationTasks($relatedTransition, $subjectType, $context, $payload);
                            $this->runActionTasks($relatedTransition, $subjectType, $context);
                        }
                    }
                    else {
                        // Fallback: if fromState is null, but it's not a generic input, just run this transition's tasks
                        $this->runRestrictionTasks($transition, $subjectType, $context);
                        $this->runValidationTasks($transition, $subjectType, $context, $payload);
                        $this->runActionTasks($transition, $subjectType, $context);
                    }
                }

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
     * Get related transitions for a state in the correct order.
     * Order: 1. Generic output (to = null), 2. Specific (from and to set), 3. Generic input (from = null)
     *
     * @param FlowState $state                       The state we're transitioning from
     * @param FlowTransitionModel $currentTransition The transition being executed
     *
     * @return Collection<FlowTransitionModel>
     */
    protected function getRelatedTransitionsForState(
        FlowState $state,
        FlowTransitionModel $currentTransition
    ): Collection {
        $result = collect();

        // 1. Generic output transitions (to = null) - transitions leaving from this state
        // Note: Terminal states don't have generic output transitions
        if (! $state->is_end) {
            $genericOutput = FlowTransitionModel::query()
                ->where('flow_id', $state->flow_id)
                ->where('from', $state->id)
                ->whereNull('to')
                ->with([
                    'tasks' => function ($query) {
                        $query->where('status', true)->orderBy('ordering');
                    },
                ])
                ->get();

            $result = $result->merge($genericOutput);
        }

        // 2. Specific transitions (from and to are set and not equal)
        // Include the current transition if it's a specific transition from this state
        if ($currentTransition->is_specific_transition && $currentTransition->from === $state->id) {
            // Load tasks if not already loaded
            if (! $currentTransition->relationLoaded('tasks')) {
                $currentTransition->load([
                    'tasks' => function ($query) {
                        $query->where('status', true)->orderBy('ordering');
                    },
                ]);
            }
            $result->push($currentTransition);
        }
        else {
            // Get all specific transitions from this state (excluding the current one if it's not specific)
            $specific = FlowTransitionModel::query()
                ->where('flow_id', $state->flow_id)
                ->where('from', $state->id)
                ->whereNotNull('to')
                ->whereColumn('from', '!=', 'to')
                ->where('id', '!=', $currentTransition->id)
                ->with([
                    'tasks' => function ($query) {
                        $query->where('status', true)->orderBy('ordering');
                    },
                ])
                ->get();

            $result = $result->merge($specific);
        }

        // 3. Generic input transitions (from = null) - transitions entering to this state
        $genericInput = FlowTransitionModel::query()
            ->where('flow_id', $state->flow_id)
            ->whereNull('from')
            ->where('to', $state->id)
            ->with([
                'tasks' => function ($query) {
                    $query->where('status', true)->orderBy('ordering');
                },
            ])
            ->get();

        return $result->merge($genericInput);
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
