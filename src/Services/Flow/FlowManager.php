<?php

namespace JobMetric\Flow\Services\Flow;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use JobMetric\Flow\Contracts\DriverContract;
use JobMetric\Flow\Exceptions\FlowDriverAlreadyExistException;
use JobMetric\Flow\Models\Flow;
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
        return Flow::create($data);

        // @todo: add first state
    }

    /**
     * show flow
     *
     * @param int $flow_id
     *
     * @return Flow
     */
    public function show(int $flow_id): Flow
    {
        return Flow::findOrFail($flow_id);
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
}
