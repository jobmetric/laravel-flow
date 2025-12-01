<?php

namespace JobMetric\Flow\Support;

use InvalidArgumentException;
use JobMetric\Flow\Contracts\AbstractTaskDriver;
use JobMetric\Flow\Models\FlowTask;
use Throwable;

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
     * @param AbstractTaskDriver $task
     *
     * @return static
     * @throws Throwable
     */
    public function register(AbstractTaskDriver $task): static
    {
        $subject = $task::subject();
        $type = FlowTask::determineTaskType($task);
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
     * @return array<string, array<string, array<string, AbstractTaskDriver>>>
     */
    public function all(): array
    {
        return $this->tasks;
    }

    /**
     * Get all registered tasks for a specific subject.
     * The returned array is keyed by type and then by task class name.
     *
     * @param string $subject
     *
     * @return array<string, array<string, AbstractTaskDriver>>
     */
    public function forSubject(string $subject): array
    {
        return $this->tasks[$subject] ?? [];
    }

    /**
     * Get all registered tasks for a specific subject and type.
     *
     * @param string $subject
     * @param string $type
     *
     * @return array<string, AbstractTaskDriver>
     * @throws Throwable
     */
    public function forSubjectAndType(string $subject, string $type): array
    {
        FlowTask::assertValidType($type);

        if (! isset($this->tasks[$subject][$type])) {
            return [];
        }

        return $this->tasks[$subject][$type];
    }

    /**
     * Determine whether a task has been registered for a given subject, type and class.
     *
     * @param string $subject
     * @param string $type
     * @param string $taskClass
     *
     * @return bool
     * @throws Throwable
     */
    public function has(string $subject, string $type, string $taskClass): bool
    {
        FlowTask::assertValidType($type);

        return isset($this->tasks[$subject][$type][$taskClass]);
    }

    /**
     * Determine whether a task class has been registered (regardless of subject/type).
     *
     * @param string $taskClass
     *
     * @return bool
     */
    public function hasClass(string $taskClass): bool
    {
        $taskClass = trim(str_replace('/', '\\', $taskClass));

        foreach ($this->tasks as $types) {
            foreach ($types as $tasks) {
                if (isset($tasks[$taskClass])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get a registered task driver instance by class name.
     *
     * @param string $taskClass
     *
     * @return AbstractTaskDriver|null
     */
    public function get(string $taskClass): ?AbstractTaskDriver
    {
        $taskClass = trim(str_replace('/', '\\', $taskClass));

        foreach ($this->tasks as $types) {
            foreach ($types as $tasks) {
                if (isset($tasks[$taskClass])) {
                    return $tasks[$taskClass];
                }
            }
        }

        return null;
    }
}
