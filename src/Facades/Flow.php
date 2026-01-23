<?php

namespace JobMetric\Flow\Facades;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin \JobMetric\Flow\Services\Flow
 *
 * @method static \JobMetric\PackageCore\Output\Response store(array $data)
 * @method static \JobMetric\PackageCore\Output\Response show(int $flowId, array $with = [])
 * @method static \JobMetric\PackageCore\Output\Response update(int $flowId, array $data, array $with = [])
 * @method static \JobMetric\PackageCore\Output\Response delete(int $flowId)
 * @method static \JobMetric\PackageCore\Output\Response restore(int $flowId)
 * @method static \JobMetric\PackageCore\Output\Response forceDelete(int $flowId)
 *
 * @method static \JobMetric\PackageCore\Output\Response toggleStatus(int $id, array $with = [])
 * @method static \JobMetric\Flow\Models\FlowState|null getStartState(int $flowId)
 * @method static \JobMetric\Flow\Models\FlowState|null getEndState(int $flowId)
 * @method static \JobMetric\PackageCore\Output\Response setDefault(int $flowId, array $with = [])
 * @method static \JobMetric\PackageCore\Output\Response setActiveWindow(int $flowId, ?Carbon $from, ?Carbon $to, array $with = [])
 * @method static \JobMetric\PackageCore\Output\Response setRollout(int $flowId, ?int $pct, array $with = [])
 * @method static \JobMetric\PackageCore\Output\Response reorder(array $orderedIds)
 * @method static \JobMetric\PackageCore\Output\Response duplicate(int $flowId, array $overrides = [], bool $withGraph = true, array $with = [])
 * @method static Collection<int, \JobMetric\Flow\Models\FlowState> getStates(int $flowId)
 * @method static array<string, \JobMetric\Flow\Models\FlowState> getStatesByStatusMap(int $flowId)
 * @method static \JobMetric\PackageCore\Output\Response validateConsistency(int $flowId)
 * @method static \JobMetric\Flow\Models\Flow|null previewPick(Model $subject, ?callable $tuner = null)
 * @method static array export(int $flowId, bool $withGraph = true)
 * @method static \JobMetric\Flow\Models\Flow import(array $payload, array $overrides = [])
 */
class Flow extends Facade
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
        return 'flow';
    }
}
