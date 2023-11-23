<?php

namespace JobMetric\Flow\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \JobMetric\Flow\Models\FlowTransition store(int $flow_id, array $data)
 *
 * @see \JobMetric\Flow\Services\FlowTransition\FlowTransitionManager
 */
class FlowTransition extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \JobMetric\Flow\Services\FlowTransition\FlowTransitionManager::class;
    }
}
