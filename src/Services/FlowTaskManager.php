<?php

namespace JobMetric\Flow\Services;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use JobMetric\Flow\Events\FlowTask\FlowRestoreEvent;
use JobMetric\Flow\Events\FlowTask\FlowTaskDeleteEvent;
use JobMetric\Flow\Events\FlowTask\FlowTaskStoreEvent;
use JobMetric\Flow\Events\FlowTask\FlowTaskUpdateEvent;
use JobMetric\Flow\Exceptions\DriverNotFoundException;
use JobMetric\Flow\Exceptions\FlowTaskNotFoundException;
use JobMetric\Flow\Http\Resources\FlowTaskDriverResource;
use JobMetric\Flow\Models\FlowTask;
use JobMetric\Metadata\JMetadata;

class FlowTaskManager
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

    public function store(array $data):FlowTask
    {
        $task = FlowTask::create($data);
        event(new FlowTaskStoreEvent($task));
        return $task;
    }

    public function update(int $flow_task_id, $data = []):FlowTask
    {
        $task=FlowTask::findOrFail($flow_task_id);
        $task->update($data);
        event(new FlowTaskUpdateEvent($task,$data));
        return $task;
    }

    public function delete(int $flow_task_id):FlowTask
    {
        $task=FlowTask::findOrFail($flow_task_id);
        $task->delete();
        event(new FlowTaskDeleteEvent($task));
        return $task;
    }


    public function show(int $flow_task_id):FlowTask
    {
        return FlowTask::findOrFail($flow_task_id);
    }

    /**
     * @throws DriverNotFoundException
     */
    public function getTasksList(string $flowDriver): array
    {
        $flowDriver=\Str::studly($flowDriver);
        return array_merge(resolveClassesFromDirectory('App\\Flows\\Global'),
            resolveClassesFromDirectory('App\\Flows\\Drivers\\'.$flowDriver.'\\Tasks'));
    }



    public function getTaskDetails(string $flowDriver = '', string $taskClassName = '')
    {

    }

    public function assignTo(int $task_id, int $transitionId)
    {

    }

}
