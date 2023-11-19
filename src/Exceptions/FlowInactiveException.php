<?php

namespace JobMetric\Flow\Exceptions;

use Exception;
use Throwable;

class FlowInactiveException extends Exception
{
    public function __construct(string $driver, int $code = 400, ?Throwable $previous = null)
    {
        $message = __('flow::base.flow.inactive', ['driver' => $driver]);

        parent::__construct($message, $code, $previous);
    }
}
