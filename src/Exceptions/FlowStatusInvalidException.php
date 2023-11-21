<?php

namespace JobMetric\Flow\Exceptions;

use Exception;
use Throwable;

class FlowStatusInvalidException extends Exception
{
    public function __construct(array $status, int $code = 400, ?Throwable $previous = null)
    {
        $message = __('flow::base.validation.check_status_in_driver', ['status' => implode(', ', $status)]);

        parent::__construct($message, $code, $previous);
    }
}
