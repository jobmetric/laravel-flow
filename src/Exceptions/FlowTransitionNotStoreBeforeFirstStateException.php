<?php

namespace JobMetric\Flow\Exceptions;

use Exception;
use Throwable;

class FlowTransitionNotStoreBeforeFirstStateException extends Exception
{
    public function __construct(int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct(__('flow::base.flow_transition.not_store_before_first_state'), $code, $previous);
    }
}
