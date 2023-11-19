<?php

namespace JobMetric\Flow\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \JobMetric\Flow\Models\FlowState store(int $flow_id, array $data)
 * @method static \JobMetric\Flow\Models\FlowState show(int $flow_state_id, array $with = [])
 *
 * @see \JobMetric\Flow\Services\FlowState\FlowStateManager
 */
class FlowState extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \JobMetric\Flow\Services\FlowState\FlowStateManager::class;
    }
}
