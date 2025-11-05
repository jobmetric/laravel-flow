<?php

namespace JobMetric\Flow\Contracts;

use JobMetric\Flow\Support\FlowTaskContext;

abstract class AbstractValidationTask extends AbstractTaskDriver
{
    /**
     * Builds the validation rule set that will be applied when this task is executed.
     *
     * @param FlowTaskContext $context
     *
     * @return array<string,mixed>
     */
    abstract public function rules(FlowTaskContext $context): array;

    /**
     * Builds an optional list of custom validation messages for the defined rule set.
     *
     * @param FlowTaskContext $context
     *
     * @return array<string,string>
     */
    public function messages(FlowTaskContext $context): array
    {
        return [];
    }

    /**
     * Builds an optional list of custom attribute names for more readable validation errors.
     *
     * @param FlowTaskContext $context
     *
     * @return array<string,string>
     */
    public function attributes(FlowTaskContext $context): array
    {
        return [];
    }
}
