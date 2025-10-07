<?php

namespace JobMetric\Flow\Events\FlowTransition;

use JobMetric\Flow\Models\FlowTransition;

readonly class FlowTransitionDeleteEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public FlowTransition $flow_transition
    )
    {
    }
}
