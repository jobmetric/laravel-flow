<?php

namespace JobMetric\Flow\Events\FlowTransition;

use JobMetric\Flow\Models\FlowTransition;

readonly class FlowTransitionStoreEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public FlowTransition $flowTransition,
    )
    {
    }
}
