<?php

namespace JobMetric\Flow\Services;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use JobMetric\Flow\Contracts\DriverContract;
use JobMetric\Flow\Enums\TableFlowStateFieldTypeEnum;
use JobMetric\Flow\Events\FlowTask\FlowRestoreEvent;
use JobMetric\Flow\Events\FlowTask\FlowTaskDeleteEvent;
use JobMetric\Flow\Events\FlowTask\FlowTaskStoreEvent;
use JobMetric\Flow\Events\FlowTask\FlowTaskUpdateEvent;
use JobMetric\Flow\Exceptions\FlowDriverAlreadyExistException;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Metadata\JMetadata;
use Str;

class FlowManager
{
    /**
     * The application instance.
     *
     * @var Application
     */
    protected Application $app;

    /**
     * The metadata instance.
     *
     * @var JMetadata
     */
    protected JMetadata $JMetadata;

    /**
     * Create a new Translation instance.
     *
     * @param Application $app
     *
     * @return void
     * @throws BindingResolutionException
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->JMetadata = $app->make('JMetadata');
    }

    /**
     * store flow
     *
     * @param array $data
     *
     * @return Flow
     */
    public function store(array $data): Flow
    {
        $flow = Flow::create($data);

        event(new FlowTaskStoreEvent($flow));

        $flowState = $flow->states()->create([
            'type' => TableFlowStateFieldTypeEnum::START(),
            'config' => [
                'color' => '#fff',
                'position' => [
                    'x' => 0,
                    'y' => 0
                ],
            ]
        ]);

        // todo: store translations for flow and start flow state

        return $flow->load('states');
    }

    /**
     * show flow
     *
     * @param int $flow_id
     * @param array $with
     *
     * @return Flow
     */
    public function show(int $flow_id, array $with = []): Flow
    {
        return Flow::findOrFail($flow_id)->load($with);
    }

    /**
     * update flow
     *
     * @param int $flow_id
     * @param array $data
     *
     * @return Flow
     * @throws FlowDriverAlreadyExistException
     */
    public function update(int $flow_id, array $data): Flow
    {
        $check = Flow::query()->where('driver', $data['driver'])->where('id', '!=', $flow_id)->first();
        if ($check) {
            throw new FlowDriverAlreadyExistException($data['driver']);
        }

        $flow = Flow::findOrFail($flow_id);

        $flow->update($data);

        event(new FlowTaskUpdateEvent($flow, $data));

        return $flow;
    }

    /**
     * delete flow
     *
     * @param int $flow_id
     *
     * @return Flow
     */
    public function delete(int $flow_id): Flow
    {
        // @todo: check exist states > 1

        $flow = Flow::findOrFail($flow_id);

        $flow->delete();

        event(new FlowTaskDeleteEvent($flow));

        return $flow;
    }

    /**
     * restore flow
     *
     * @param int $flow_id
     *
     * @return Flow
     */
    public function restore(int $flow_id): Flow
    {
        /** @var Flow $flow */
        $flow = Flow::query()->withTrashed()->findOrFail($flow_id);

        $flow->restore();

        event(new FlowRestoreEvent($flow));

        return $flow;
    }

    /**
     * force delete flow
     *
     * @param int $flow_id
     *
     * @return Flow
     */
    public function forceDelete(int $flow_id): Flow
    {
        /** @var Flow $flow */
        $flow = Flow::query()->withTrashed()->findOrFail($flow_id);

        $flow->forceDelete();

        event(new FlowTaskDeleteEvent($flow));

        return $flow;
    }

    /**
     * Resolve the given flow instance by name.
     *
     * @param string $driver
     *
     * @return DriverContract
     */
    public function getDriver(string $driver): DriverContract
    {
        $driver = Str::studly($driver);

        if ($driver == 'Global') {
            $instance = resolve("\\JobMetric\\Flow\\Flows\\Global\\GlobalDriverFlow");
        } else {
            $instance = resolve("\\App\\Flows\\Drivers\\$driver\\{$driver}DriverFlow");
        }

        return $instance;
    }

    /**
     * Get the status of the given flow instance by name.
     *
     * @param string $driver
     *
     * @return array
     */
    public function getStatus(string $driver): array
    {
        return $this->getDriver($driver)->getStatus();
    }

    /**
     * Get start state
     *
     * @param int $flow_id
     *
     * @return FlowState|null
     */
    public function getStartState(int $flow_id): FlowState|null
    {
        /* @var FlowState $flow_state */
        $flow_state = FlowState::query()->where([
            'flow_id' => $flow_id,
            'type' => TableFlowStateFieldTypeEnum::START()
        ])->first();

        return $flow_state;
    }
}
