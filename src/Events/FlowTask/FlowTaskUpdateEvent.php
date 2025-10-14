<?php

namespace JobMetric\Flow\Events\FlowTask;

use JobMetric\Flow\Models\FlowTask;

readonly class FlowTaskUpdateEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public FlowTask $flowTask,
        public array    $data
    )
    {
    }
}
