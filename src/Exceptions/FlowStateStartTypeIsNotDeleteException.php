<?php

namespace JobMetric\Flow\Exceptions;

use Exception;
use Throwable;

class FlowStateStartTypeIsNotDeleteException extends Exception
{
    public function __construct(int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct(__('flow::base.flow_state.start_type_is_not_delete'), $code, $previous);
    }
}
