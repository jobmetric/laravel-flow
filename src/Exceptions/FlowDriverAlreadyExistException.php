<?php

namespace JobMetric\Flow\Exceptions;

use Exception;
use Throwable;

class FlowDriverAlreadyExistException extends Exception
{
    public function __construct(string $driver, int $code = 400, ?Throwable $previous = null)
    {
        $message = 'Flow driver "' . $driver . '" already exist.';

        parent::__construct($message, $code, $previous);
    }
}
