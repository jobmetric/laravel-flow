<?php

namespace JobMetric\Flow\Exceptions;

use Exception;
use Throwable;

class InvalidRolloutException extends Exception
{
    public function __construct(int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct(trans('workflow::base.exceptions.invalid_rollout'), $code, $previous);
    }
}
