<?php

namespace JobMetric\Flow\Exceptions;

use Exception;
use Throwable;

class FlowStateStartTypeIsExistException extends Exception
{
    public function __construct(string $driver, int $code = 400, ?Throwable $previous = null)
    {
        $message = __('flow::base.flow_state.start_type_is_exist', ['driver' => $driver]);

        parent::__construct($message, $code, $previous);
    }
}
