<?php

namespace JobMetric\Flow\Contracts;

use Illuminate\Database\Eloquent\Model;
use JobMetric\Flow\Support\TaskRegistry;

/**
 * Class AbstractFlowTask
 *
 * Base class for all Flow tasks.
 * Each task represents a discrete unit of logic that can be applied
 * to a specific Eloquent model and registered in the TaskRegistry.
 *
 * Tasks extending this class must define a human-readable title,
 * description, and implement their main execution logic in `handle()`.
 */
abstract class AbstractFlowTask
{
    /**
     * Get the title of the task.
     *
     * @return string
     */
    abstract public static function title(): string;

    /**
     * Get a human-readable description of the task.
     *
     * @return string
     */
    abstract public static function description(): string;

    /**
     * Define the model class that this task applies to.
     *
     * Return `null` if the task is global and not bound to any specific model.
     *
     * @return string|null Fully qualified model class name or null.
     */
    public static function modelType(): ?string
    {
        return null;
    }

    /**
     * Define the operation types that this task supports.
     *
     * Example: ["create", "update", "delete", "validate"]
     *
     * @return string[]
     */
    public function operations(): array
    {
        return [];
    }

    /**
     * Determine if the task should run in the background (asynchronous).
     *
     * @return bool
     */
    public function isBackground(): bool
    {
        return false;
    }

    /**
     * Execute the main logic of the task.
     *
     * @param Model $model The Eloquent model instance this task operates on.
     * @return void
     */
    abstract public function handle(Model $model): void;

    /**
     * Retrieve all registered tasks from the TaskRegistry.
     *
     * @return AbstractFlowTask[] List of registered task instances.
     */
    public static function registeredTasks(): array
    {
        /** @var TaskRegistry $registry */
        $registry = app(TaskRegistry::class);

        return $registry->all();
    }
}
