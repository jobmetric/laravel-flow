<?php

namespace JobMetric\Flow\Exceptions;

use Exception;
use Throwable;

class FlowTransitionStateDriverFromAndToNotEqualException extends Exception
{
    public function __construct(int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct(__('flow::base.flow_transition.state_driver_from_and_to_not_equal'), $code, $previous);
    }
}
