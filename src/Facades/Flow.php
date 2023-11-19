<?php

namespace JobMetric\Flow\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \JobMetric\Flow\Models\Flow store(array $data)
 * @method static \JobMetric\Flow\Models\Flow show(int $flow_id)
 * @method static \JobMetric\Flow\Models\Flow update(int $flow_id, array $data)
 * @method static \JobMetric\Flow\Models\Flow delete(int $flow_id)
 * @method static \JobMetric\Flow\Models\Flow restore(int $flow_id)
 * @method static \JobMetric\Flow\Models\Flow forceDelete(int $flow_id)
 * @method static \JobMetric\Flow\Contracts\DriverContract getDriver(string $driver)
 * @method static array getStatus(string $driver)
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
