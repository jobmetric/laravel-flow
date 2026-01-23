<?php

namespace JobMetric\Flow\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \JobMetric\Flow\Services\FlowState
 *
 * @method static \JobMetric\PackageCore\Output\Response store(array $data)
 * @method static \JobMetric\PackageCore\Output\Response show(int $id, array $with = [])
 * @method static \JobMetric\PackageCore\Output\Response update(int $id, array $data, array $with = [])
 * @method static \JobMetric\PackageCore\Output\Response destroy(int $id, array $with = [])
 */
class FlowState extends Facade
{
    /**
     * Get the registered name of the component in the service container.
     *
     * This accessor must match the binding defined in the package service provider.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'flow-state';
    }
}
