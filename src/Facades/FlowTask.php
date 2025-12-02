<?php

namespace JobMetric\Flow\Facades;

use Illuminate\Support\Facades\Facade;
use JobMetric\Flow\Services\FlowTask as FlowTaskService;

/**
 * @method static \JobMetric\Flow\Models\FlowTask store(int $flow_id, int $flow_transition_id, array $data)
 * @method static \JobMetric\Flow\Models\FlowTask show(int $flow_task_id)
 * @method static \JobMetric\Flow\Models\FlowTask update(int $flow_task_id, array $data)
 * @method static \JobMetric\Flow\Models\FlowTask delete(int $flow_task_id)
 * @method static array drivers(string $taskDriver = '', array|string|null $taskTypes = null)
 * @method static array details(string $taskDriver, string $taskClassName)
 * @method static \JobMetric\Flow\Contracts\AbstractTaskDriver|null resolveDriver(string $driverClass)
 *
 * @see \JobMetric\Flow\Services\FlowTaskService
 */
class FlowTask extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return FlowTaskService::class;
    }
}
