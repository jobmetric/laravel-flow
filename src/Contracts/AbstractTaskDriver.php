<?php

namespace JobMetric\Flow\Contracts;

use JobMetric\Form\FormBuilder;

abstract class AbstractTaskDriver
{
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
}
