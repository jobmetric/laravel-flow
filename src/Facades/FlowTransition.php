<?php

namespace JobMetric\Flow\Facades;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin \JobMetric\Flow\Services\FlowTransition
 *
 * @method static \JobMetric\PackageCore\Output\Response store(array $data)
 * @method static \JobMetric\PackageCore\Output\Response show(int $id, array $with = [])
 * @method static \JobMetric\PackageCore\Output\Response update(int $id, array $data, array $with = [])
 * @method static \JobMetric\PackageCore\Output\Response destroy(int $id, array $with = [])
 * @method static \JobMetric\Flow\DTO\TransitionResult runner(int|string $key, ?Model $subject = null, array $payload = [], ?Authenticatable $user = null)
 */
class FlowTransition extends Facade
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
        return 'flow-transition';
    }
}
