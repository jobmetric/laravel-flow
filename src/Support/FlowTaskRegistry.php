<?php

namespace JobMetric\Flow\Support;

use InvalidArgumentException;
//use JobMetric\Flow\Contracts\TaskAbstract;
use JobMetric\Flow\Contracts\AbstractTaskDriver;


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
     * @var AbstractTaskDriver[]
     */
    protected array $tasks = [];

    /**
     * Register a new Flow task.
     *
     * Accepts either an instance of AbstractFlowTask or a fully qualified class name.
     * Prevents duplicate registration of the same task class.
     *
     * @param AbstractTaskDriver $task
     *
     * @return static
     */
    public function register(AbstractTaskDriver $task): static
    {
        $subject = $task::subject();

        if ($task instanceof \JobMetric\Flow\Contracts\AbstractActionTask) {
            $type = 'action';
        } elseif ($task instanceof \JobMetric\Flow\Contracts\AbstractValidationTask) {
            $type = 'validation';
        } elseif ($task instanceof \JobMetric\Flow\Contracts\AbstractRestrictionTask) {
            $type = 'restriction';
        } else {
            throw new InvalidArgumentException("Task must extend a known Task type");
        }

        if(! isset($this->tasks[$subject][$type])) {
            $this->tasks[$subject][$type] = [];
        }

        foreach ($this->tasks[$subject][$type] as $existingTask) {
            if ($existingTask === $task) {
                throw new \InvalidArgumentException("This Task instance is already registered for model '{$subject}' and type '{$type}'");
            }
        }

        $this->tasks[$subject][$type][] = $task;

        return $this;
    }

    /**
     * Get all tasks associated with a specific model.
     *
     * @param string $subject Fully qualified class name of the model.
     *
     * @return array
     */
    public function all(string $subject): array
    {
        return $this->tasks[$subject] ?? [];
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
