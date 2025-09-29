<?php

namespace JobMetric\Flow\Services\Pipelines\Contracts;

use Closure;

/**
 * Interface FlowTransitionContract
 *
 * Defines a single responsibility step for the Flow Transition pipeline.
 */
interface FlowTransitionContract
{
	/**
	 * Handle the flow transition payload and pass to the next pipe.
	 *
	 * @param array $payload The evolving order context (mutable pipeline bag).
	 * @param Closure $next The next pipe.
	 *
	 * @return mixed
	 */
	public function handle(array $payload, Closure $next): mixed;
}
