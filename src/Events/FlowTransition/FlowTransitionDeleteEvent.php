<?php

namespace JobMetric\Flow\Events\FlowTransition;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JobMetric\Flow\Models\FlowTransition;

class FlowTransitionDeleteEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly FlowTransition $flow_transition
    )
    {
    }
}
