<?php

namespace JobMetric\Flow\Events\FlowTransition;

use JobMetric\Flow\Models\FlowTransition;

readonly class FlowTransitionUpdateEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public FlowTransition $flowTransition,
        public array          $data
    )
    {
    }
}
