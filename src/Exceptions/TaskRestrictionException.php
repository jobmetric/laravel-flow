<?php

namespace JobMetric\Flow\Exceptions;

use Exception;
use Throwable;

class TaskRestrictionException extends Exception
{
    public function __construct(string $message = null, int $code = 400, ?Throwable $previous = null)
    {
        $message = $message ?? 'workflow::base.exceptions.task_restriction';

        parent::__construct(trans($message), $code, $previous);
    }
}
