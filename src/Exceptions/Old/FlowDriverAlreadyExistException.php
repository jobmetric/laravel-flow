<?php

namespace JobMetric\Flow\Exceptions\Old;

use Exception;
use Throwable;

class FlowDriverAlreadyExistException extends Exception
{
    public function __construct(string $driver, int $code = 400, ?Throwable $previous = null)
    {
        $message = __('flow::base.flow.exist', ['driver' => $driver]);

        parent::__construct($message, $code, $previous);
    }
}
