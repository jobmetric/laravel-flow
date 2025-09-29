<?php

namespace JobMetric\Flow\Services\Pipelines\FlowTransition;

use Closure;
use JobMetric\Flow\Services\Pipelines\Contracts\FlowTransitionContract;

class ValidateData implements FlowTransitionContract
{
    /**
     * Handle the flow transition payload and pass to the next pipe.
     *
     * @param array $payload The evolving order context (mutable pipeline bag).
     * @param Closure $next The next pipe.
     *
     * @return mixed
     */
    public function handle(array $payload, Closure $next): mixed
    {
        return $next($payload);
    }
}
