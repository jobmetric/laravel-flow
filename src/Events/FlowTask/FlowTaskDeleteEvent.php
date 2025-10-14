<?php

namespace JobMetric\Flow\Events\FlowTask;

use JobMetric\Flow\Models\FlowTask;

readonly class FlowTaskDeleteEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public FlowTask $flowTask
    )
    {
    }
}
