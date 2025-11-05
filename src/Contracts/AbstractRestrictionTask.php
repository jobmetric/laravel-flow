<?php

namespace JobMetric\Flow\Contracts;

use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\RestrictionResult;

abstract class AbstractRestrictionTask extends AbstractTaskDriver
{
    /**
     * Evaluates whether the current operation is allowed within the provided flow context.
     *
     * @param FlowTaskContext $context
     *
     * @return RestrictionResult
     */
    abstract public function restriction(FlowTaskContext $context): RestrictionResult;
}
