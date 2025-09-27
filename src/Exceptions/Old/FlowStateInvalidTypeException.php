<?php

namespace JobMetric\Flow\Exceptions\Old;

use Exception;
use Throwable;

class FlowStateInvalidTypeException extends Exception
{
    public function __construct(string $type, int $code = 400, ?Throwable $previous = null)
    {
        $message = __('flow::base.flow_state.invalid_type', ['type' => $type]);

        parent::__construct($message, $code, $previous);
    }
}
