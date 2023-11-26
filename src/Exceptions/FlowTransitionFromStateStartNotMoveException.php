<?php

namespace JobMetric\Flow\Exceptions;

use Exception;
use Throwable;

class FlowTransitionFromStateStartNotMoveException extends Exception
{
    public function __construct(int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct(__('flow::base.flow_transition.from_state_start_not_move'), $code, $previous);
    }
}
