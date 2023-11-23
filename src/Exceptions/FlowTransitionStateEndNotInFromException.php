<?php

namespace JobMetric\Flow\Exceptions;

use Exception;
use Throwable;

class FlowTransitionStateEndNotInFromException extends Exception
{
    public function __construct(int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct(__('flow::base.flow_transition.state_end_not_in_from'), $code, $previous);
    }
}
