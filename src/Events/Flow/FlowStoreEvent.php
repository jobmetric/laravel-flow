<?php

namespace JobMetric\Flow\Events\Flow;

use JobMetric\Flow\Models\Flow;

readonly class FlowStoreEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public Flow $flow
    )
    {
    }
}
