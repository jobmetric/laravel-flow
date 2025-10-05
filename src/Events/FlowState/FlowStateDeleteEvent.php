<?php

namespace JobMetric\Flow\Events\FlowState;

use JobMetric\Flow\Models\FlowState;

readonly class FlowStateDeleteEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public FlowState $flowState
    )
    {
    }
}
