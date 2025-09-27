<?php

namespace JobMetric\Flow\Exceptions\Old;

use Exception;
use Throwable;

class FlowTransitionSlugExistException extends Exception
{
    public function __construct(string $slug, int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct(__('flow::base.flow_transition.slug_is_exist', ['slug' => $slug]), $code, $previous);
    }
}
