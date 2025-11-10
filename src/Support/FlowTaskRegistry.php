<?php

namespace JobMetric\Flow\Support;

use InvalidArgumentException;
use JobMetric\Flow\Contracts\AbstractActionTask;
use JobMetric\Flow\Contracts\AbstractRestrictionTask;
use JobMetric\Flow\Contracts\AbstractTaskDriver;
use JobMetric\Flow\Contracts\AbstractValidationTask;

/**
 * FlowTaskRegistry
 *
 * Central registry for all flow task drivers.
 * Tasks are indexed by their subject (usually a model FQCN), their logical type
 * (action, validation, restriction) and their concrete class name.
 * This provides predictable lookup, prevents duplicate registrations and enables
 * efficient retrieval of tasks per subject and per type.
 */
class FlowTaskRegistry
{
    public const TYPE_ACTION = 'action';
    public const TYPE_VALIDATION = 'validation';
    public const TYPE_RESTRICTION = 'restriction';

    /**
     * Internal map of registered tasks.
     * The first level key is the subject (typically the model FQCN),
     * the second level key is the task type, and the third level key is the task class name.
     *
     * @var array<string, array<string, array<string, AbstractTaskDriver>>>
     */
    protected array $tasks = [];

    /**
     * Register a new flow task driver instance.
     * The task will be indexed by its subject, its resolved type and its concrete class name.
     * If the same task class is already registered for the same subject and type, an exception is thrown.
     *
     * @param AbstractTaskDriver $task Concrete task instance to be registered.
     *
     * @return static
     * @throws InvalidArgumentException If the subject is empty or the task has already been registered.
     */
    public function register(AbstractTaskDriver $task): static
    {
        $subject = $task::subject();
        $type = $this->determineTaskType($task);
        $class = get_class($task);

        if ($subject === '') {
            throw new InvalidArgumentException('Task subject must not be an empty string.');
        }

        if (! isset($this->tasks[$subject])) {
            $this->tasks[$subject] = [];
        }

        if (! isset($this->tasks[$subject][$type])) {
            $this->tasks[$subject][$type] = [];
        }

        if (isset($this->tasks[$subject][$type][$class])) {
            throw new InvalidArgumentException("Task '{$class}' is already registered for subject '{$subject}' and type '{$type}'.");
        }

        $this->tasks[$subject][$type][$class] = $task;

        return $this;
    }

    /**
     * Get all registered tasks grouped by subject, type and class name.
     *
     * @return array<string, array<string, array<string, AbstractTaskDriver>>> Full task map.
     */
    public function all(): array
    {
        return $this->tasks;
    }

    /**
     * Get all registered tasks for a specific subject.
     * The returned array is keyed by type and then by task class name.
     *
     * @param string $subject Subject identifier (typically the model FQCN).
     *
     * @return array<string, array<string, AbstractTaskDriver>> Map of type => [class => task].
     */
    public function forSubject(string $subject): array
    {
        return $this->tasks[$subject] ?? [];
    }

    /**
     * Get all registered tasks for a specific subject and type.
     *
     * @param string $subject Subject identifier (typically the model FQCN).
     * @param string $type    Logical task type: "action", "validation" or "restriction".
     *
     * @return array<string, AbstractTaskDriver> Map of class => task instance.
     * @throws InvalidArgumentException If an unknown task type is requested.
     */
    public function forSubjectAndType(string $subject, string $type): array
    {
        $this->assertValidType($type);

        if (! isset($this->tasks[$subject][$type])) {
            return [];
        }

        return $this->tasks[$subject][$type];
    }

    /**
     * Determine whether a task has been registered for a given subject, type and class.
     *
     * @param string $subject   Subject identifier (typically the model FQCN).
     * @param string $type      Logical task type: "action", "validation" or "restriction".
     * @param string $taskClass Fully qualified class name of the task.
     *
     * @return bool True when the task exists for the given subject and type.
     * @throws InvalidArgumentException If an unknown task type is requested.
     */
    public function has(string $subject, string $type, string $taskClass): bool
    {
        $this->assertValidType($type);

        return isset($this->tasks[$subject][$type][$taskClass]);
    }

    /**
     * Resolve the logical task type for the given driver instance.
     * This method maps the concrete abstract base class to a normalized string type.
     *
     * @param AbstractTaskDriver $task Task instance to inspect.
     *
     * @return string One of "action", "validation" or "restriction".
     * @throws InvalidArgumentException If the task does not extend a known base task type.
     */
    protected function determineTaskType(AbstractTaskDriver $task): string
    {
        if ($task instanceof AbstractActionTask) {
            return self::TYPE_ACTION;
        }

        if ($task instanceof AbstractValidationTask) {
            return self::TYPE_VALIDATION;
        }

        if ($task instanceof AbstractRestrictionTask) {
            return self::TYPE_RESTRICTION;
        }

        throw new InvalidArgumentException(sprintf("Task '%s' must extend one of [%s, %s, %s].", get_class($task), AbstractActionTask::class, AbstractValidationTask::class, AbstractRestrictionTask::class));
    }

    /**
     * Ensure that the given type string is one of the supported task types.
     *
     * @param string $type Logical task type string to validate.
     *
     * @return void
     * @throws InvalidArgumentException If the type is not one of the supported values.
     */
    protected function assertValidType(string $type): void
    {
        $valid = [
            self::TYPE_ACTION,
            self::TYPE_VALIDATION,
            self::TYPE_RESTRICTION,
        ];

        if (! in_array($type, $valid, true)) {
            throw new InvalidArgumentException("Invalid task type '{$type}'. Allowed types are: '" . implode("', '", $valid) . "'.");
        }
    }
}
