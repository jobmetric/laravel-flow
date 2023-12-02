<?php

namespace JobMetric\Flow\Services;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\File;
use JobMetric\Flow\Contracts\TaskContract;
use JobMetric\Flow\Events\FlowTask\FlowTaskDeleteEvent;
use JobMetric\Flow\Events\FlowTask\FlowTaskStoreEvent;
use JobMetric\Flow\Events\FlowTask\FlowTaskUpdateEvent;
use JobMetric\Flow\Models\FlowTask;
use JobMetric\Metadata\JMetadata;
use Str;

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

    public function store(array $data): FlowTask
    {
        // @todo: add exception

        $task = FlowTask::create($data);

        event(new FlowTaskStoreEvent($task));

        return $task;
    }

    public function update(int $flow_task_id, $data = []): FlowTask
    {
        // @todo: add exception

        $task = FlowTask::findOrFail($flow_task_id);

        $task->update($data);

        event(new FlowTaskUpdateEvent($task, $data));

        return $task;
    }

    public function delete(int $flow_task_id): FlowTask
    {
        $task = FlowTask::findOrFail($flow_task_id);
        $task->delete();
        event(new FlowTaskDeleteEvent($task));
        return $task;
    }


    public function show(int $flow_task_id): FlowTask
    {
        return FlowTask::findOrFail($flow_task_id);
    }

    /**
     * get all flow task drivers
     *
     * @param string $flow_driver
     *
     * @return array
     */
    public function drivers(string $flow_driver): array
    {
        $flow_driver = Str::studly($flow_driver);
        $driver = flowResolve($flow_driver);

        $global_tasks = [];
        $results = $this->resolveClassesFromDirectory('App\\Flows\\Global');
        foreach ($results as $result) {
            $global_tasks[] = $this->getDetailsFromTask($result, false);
        }

        $driver_tasks = [];
        $results = $this->resolveClassesFromDirectory('App\\Flows\\Drivers\\' . $flow_driver . '\\Tasks');
        foreach ($results as $result) {
            $driver_tasks[] = $this->getDetailsFromTask($result, false);
        }

        return [
            [
                'key' => 'Global',
                'title' => __('flow::base.flow_task.global'),
                'tasks' => $global_tasks
            ],
            [
                'key' => $flow_driver,
                'title' => $driver->getTitle(),
                'tasks' => $driver_tasks
            ]
        ];
    }

    /**
     * restore flow task driver with namespace
     *
     * @param string $namespace
     *
     * @return array
     */
    private function resolveClassesFromDirectory(string $namespace): array
    {
        $files = File::files(base_path($namespace), '*.php');

        $class = [];
        foreach ($files as $file) {
            $class[] = resolve($namespace . '\\' . pathinfo($file, PATHINFO_FILENAME));
        }

        return $class;
    }

    private function getDetailsFromTask(TaskContract $task, bool $has_field = true): array
    {
        return array_merge([
            'key' => class_basename($task),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
        ], $has_field ? ['fields' => $task->getFields()] : []);
    }
}
