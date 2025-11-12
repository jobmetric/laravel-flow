<?php

namespace JobMetric\Flow\Contracts;

use JobMetric\Form\FormBuilder;

abstract class AbstractTaskDriver
{
    const TASK_TYPE_RESTRICTION = 'restriction';
    const TASK_TYPE_VALIDATION = 'validation';
    const TASK_TYPE_ACTION = 'action';

    /**
     * Returns the Eloquent model class that this task operates on.
     *
     * @return string
     */
    abstract public static function subject(): string;

    /**
     * Returns the translation key that represents the display title of the task.
     *
     * @return string
     */
    abstract public static function title(): string;

    /**
     * Returns the translation key that represents the human-readable description of the task.
     *
     * @return string|null
     */
    public static function description(): ?string
    {
        return null;
    }

    /**
     * Registers configuration fields for this task using the provided form builder instance.
     *
     * @param FormBuilder $form
     *
     * @return void
     */
    abstract public function form(FormBuilder $form): void;

    /**
     * Determines the task type based on which abstract task contract this class extends.
     * Role: It inspects the current instance and returns a normalized string identifier
     * for the task type to allow routing/dispatching logic and UI labeling.
     *
     * @return string Returns one of: "action", "restriction", "validation", or "unknown".
     */
    public function taskType(): string
    {
        return match (true) {
            $this instanceof AbstractActionTask => self::TASK_TYPE_ACTION,
            $this instanceof AbstractRestrictionTask => self::TASK_TYPE_RESTRICTION,
            $this instanceof AbstractValidationTask => self::TASK_TYPE_VALIDATION,
            default => 'unknown',
        };
    }
}
