<?php

namespace JobMetric\Flow\Events\FlowState;

use JobMetric\Flow\Models\FlowState;

readonly class FlowStateUpdateEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public FlowState $flowState,
        public array     $data
    )
    {
    }
}
