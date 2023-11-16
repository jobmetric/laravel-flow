<?php

namespace JobMetric\Flow\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \JobMetric\Flow\Models\Flow store(array $data)
 *
 * @see \JobMetric\Flow\Services\Flow\FlowManager
 */
class Flow extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \JobMetric\Flow\Services\Flow\FlowManager::class;
    }
}
