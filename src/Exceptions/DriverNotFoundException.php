<?php

namespace JobMetric\Flow\Exceptions;

use Exception;
use Throwable;

class DriverNotFoundException extends Exception
{

    /**
     * @param mixed $driver
     */
    public function __construct(string $driver, int $code = 400, ?Throwable $previous = null)
    {
        $message = __('flow::base.flow.not_found', ['driver' => $driver]);

        parent::__construct($message, $code, $previous);
    }
}