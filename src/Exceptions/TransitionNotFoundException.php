<?php

namespace JobMetric\Flow\Exceptions;

use Exception;
use Throwable;

class TransitionNotFoundException extends Exception
{
    public function __construct(int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct(trans('workflow::base.exceptions.transition_not_found'), $code, $previous);
    }
}
