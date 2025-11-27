<?php

namespace JobMetric\Flow\Contracts;

use JobMetric\Flow\Support\FlowTaskDefinition;
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
     * Returns the full metadata definition for this flow task.
     *
     * @return FlowTaskDefinition
     */
    abstract public static function definition(): FlowTaskDefinition;

    /**
     * Registers configuration fields for this task using the provided form builder instance.
     *
     * @param FormBuilder $form
     *
     * @return void
     */
    abstract public function form(FormBuilder $form): void;
}
