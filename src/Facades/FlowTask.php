<?php

namespace JobMetric\Flow\Facades;

use Illuminate\Support\Facades\Facade;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Services\FlowTaskManager;

/**
 * @method static \JobMetric\Flow\Models\FlowTask store(int $flow_id, int $flow_transition_id, array $data)
 * @method static \JobMetric\Flow\Models\FlowTask show(int $flow_task_id)
 * @method static \JobMetric\Flow\Models\FlowTask update(int $flow_task_id, array $data)
 * @method static \JobMetric\Flow\Models\FlowTask delete(int $flow_task_id)
 * @method static array drivers(string $flow_driver = '')
 * @method static array details(string $flow_driver, string $task_driver)
 *
 * @see \JobMetric\Flow\Services\FlowTaskManager
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
        return FlowTaskManager::class;
    }
}
