<?php

namespace JobMetric\Flow\Events\Flow;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JobMetric\Flow\Models\Flow;

class FlowRestoreEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Flow $flow
    )
    {
    }
}
