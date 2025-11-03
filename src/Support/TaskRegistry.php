<?php

namespace JobMetric\Flow\Support;

use JobMetric\Flow\Contracts\AbstractFlowTask;
use Illuminate\Support\Facades\Log;


/**
 * Class TaskRegistry
 *
 * Manages registration and retrieval of Flow tasks.
 * Tasks can be registered globally and filtered by model or operation type.
 */
class TaskRegistry
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
     * @param AbstractFlowTask|string $task
     * @return void
     */
    public function register(AbstractFlowTask|string $task): void
{
    if (is_string($task)) {
        if (! class_exists($task)) {
            return;
        }
        // use container to allow DI
        $task = app()->make($task);
    }

    if (! $task instanceof AbstractFlowTask) {
        return;
    }

    foreach ($this->tasks as $existing) {
        if (get_class($existing) === get_class($task)) {
            return;
        }
    }

    $this->tasks[] = $task;
}

    /**
     * Get all registered tasks.
     *
     * @return AbstractFlowTask[]
     */
    public function all(): array
    {
        return array_values($this->tasks);
    }

    /**
     * Get all tasks associated with a specific model.
     *
     * @param string $model Fully qualified class name of the model.
     * @return AbstractFlowTask[]
     */
    public function forModel(string $model): array
{
    $filtered = array_filter($this->tasks, function (AbstractFlowTask $task) use ($model) {
        $taskModel = $task::modelType();
        if (is_null($taskModel)) {
            // global task applies to all models? decide policy: include or exclude
            return false; // or true if global applies to any
        }
        // model can be subclass or same
        return is_a($model, $taskModel, true) || is_a($taskModel, $model, true);
    });

    return array_values($filtered);
}


    /**
     * Get all tasks available for a given operation type.
     *
     * @param string $operation Operation name (e.g. "create", "update").
     * @return AbstractFlowTask[]
     */
    public function forOperation(string $operation): array
    {
        $filtered = array_filter($this->tasks, function ($task) use ($operation) {
            if (! is_object($task)) {
                return false;
            }

            $ops = $task->operations() ?? [];

            return is_array($ops) && in_array($operation, $ops, true);
        });

        return array_values($filtered);
    }

    /**
 * Run all registered tasks for the given model and operation.
 *
 * Executes both synchronous and background (queued) tasks
 * registered for the model and operation type.
 *
 * @param \Illuminate\Database\Eloquent\Model $model
 * @param string $operation
 * @return void
 */
public function run(\Illuminate\Database\Eloquent\Model $model, string $operation): void
{
    foreach ($this->forModel(get_class($model)) as $task) {
        if (! in_array($operation, $task->operations(), true)) {
            continue;
        }

        try {
            if ($task->isBackground()) {
                // Run asynchronously using Laravel queue
                dispatch(function() use ($task, $model) {
                    try {
                        $task->handle($model);
                    } catch (\Throwable $e) {
                        Log::error(sprintf(
                            '[Flow] Background task "%s" failed: %s',
                            get_class($task),
                            $e->getMessage()
                        ), [
                            'model' => get_class($model),
                            'task' => get_class($task),
                            'exception' => $e,
                        ]);
                    }
                });
            } else {
                // Run synchronously
                $task->handle($model);
            }
        } catch (\Throwable $e) {
            // Log but don't stop other tasks
            Log::error(sprintf(
                '[Flow] Task "%s" failed during "%s" operation: %s',
                get_class($task),
                $operation,
                $e->getMessage()
            ), [
                'model' => get_class($model),
                'task' => get_class($task),
                'operation' => $operation,
                'exception' => $e,
            ]);

            // Optional: decide whether to stop execution or continue
            // throw $e; // uncomment if you want to stop on first failure
        }
    }
}

}
