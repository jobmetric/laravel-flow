<?php

namespace JobMetric\Flow\Exceptions\Old;

use Exception;
use Throwable;

class FlowTransitionHaveAtLeastOneTransitionFromTheStartBeginningException extends Exception
{
    public function __construct(int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct(__('flow::base.flow_transition.have_at_least_one_transition_from_the_start_beginning'), $code, $previous);
    }
}
