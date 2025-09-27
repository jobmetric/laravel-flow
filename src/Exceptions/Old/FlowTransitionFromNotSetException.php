<?php

namespace JobMetric\Flow\Exceptions\Old;

use Exception;
use Throwable;

class FlowTransitionFromNotSetException extends Exception
{
    public function __construct(int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct(__('flow::base.flow_transition.from_not_set'), $code, $previous);
    }
}
