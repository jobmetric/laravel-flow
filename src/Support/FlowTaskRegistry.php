<?php

namespace JobMetric\Flow\Support;

use InvalidArgumentException;
use JobMetric\Flow\Abstracts\AbstractFlowTask;
use JobMetric\Flow\Abstracts\TaskAbstract;


/**
 * Class FlowTaskRegistry
 *
 * Manages registration and retrieval of Flow tasks.
 * Tasks can be registered globally and filtered by model or operation type.
 */
class FlowTaskRegistry
{
    /**
     * List of all registered tasks.
     *
     * @var AbstractFlowTask[]
     */
    protected array $tasks = [];

    /**
     * Register a new Flow task.
     *
     * Accepts either an instance of AbstractFlowTask or a fully qualified class name.
     * Prevents duplicate registration of the same task class.
     *
     * @param TaskAbstract $task
     *
     * @return static
     */
    public function register(TaskAbstract $task): static
    {
        $model = get_class($task->model);
        $type = $task->type->value;
        $task = get_class($task);

        if (isset($this->tasks[$model][$type][$task])) {
            throw new InvalidArgumentException("Task '{$task}' already registered for model '{$model}' and type '{$type}'.");
        }

        $this->tasks[$model][$type][$task] = $task;

        return $this;
    }

    /**
     * Get all tasks associated with a specific model.
     *
     * @param string $model Fully qualified class name of the model.
     *
     * @return array
     */
    public function all(string $model): array
    {
        return $this->tasks[$model] ?? [];
    }

    /**
     * Run all registered tasks for the given model and operation.
     *
     * Executes both synchronous and background (queued) tasks
     * registered for the model and operation type.
     *
     * @param array $config Configuration array for the task
     *
     * @return void
     */
    public function run(array $config): void
    {

    }
}
