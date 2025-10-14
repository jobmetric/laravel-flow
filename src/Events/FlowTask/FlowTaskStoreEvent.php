<?php

namespace JobMetric\Flow\Events\FlowTask;

use JobMetric\Flow\Models\FlowTask;

readonly class FlowTaskStoreEvent
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
