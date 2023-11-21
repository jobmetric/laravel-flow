<?php

namespace JobMetric\Flow\Events\FlowState;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JobMetric\Flow\Models\FlowState;

class FlowStateDeleteEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly FlowState $flow_state
    )
    {
    }
}
