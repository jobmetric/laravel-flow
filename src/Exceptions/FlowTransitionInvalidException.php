<?php

namespace JobMetric\Flow\Exceptions;

use Exception;
use Throwable;

class FlowTransitionInvalidException extends Exception
{
    public function __construct(int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct(__('flow::base.flow_transition.invalid'), $code, $previous);
    }
}
