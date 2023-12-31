<?php

namespace JobMetric\Flow\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use JobMetric\PackageCore\Commands\ConsoleTools;

class MakeTask extends Command
{
    use ConsoleTools;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:flow-task
                {task : Flow task name}
                {driver? : Flow driver name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Flow Task';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $task = $this->argument('task');
        $task = Str::studly($task);

        $driver = $this->argument('driver');
        if ($driver) {
            // make flow task
            $driver = Str::studly($driver);

            Artisan::call('make:flow ' . $driver);

            if ($this->isFile('app/Flows/Drivers/' . $driver . '/Tasks/' . $task . $driver . 'Task.php')) {
                $this->message('Flow task already exists.', 'error');

                return 1;
            }

            $content_task = $this->getStub(__DIR__ . '/stub/task', [
                'task' => $task,
                'driver' => $driver,
            ]);

            $path = base_path('app/Flows/Drivers/' . $driver . '/Tasks');
            if (!$this->isDir($path)) {
                $this->makeDir($path);
            }

            if (!$this->isFile($path . '/' . $task . $driver . 'Task.php')) {
                $this->putFile($path . '/' . $task . $driver . 'Task.php', $content_task);
            }

            $this->message('Flow Task <options=bold>[' . $path . '/' . $task . $driver . 'Task.php]</> created successfully.', 'success');
        } else {
            // make global task
            if ($this->isFile('app/Flows/Global/' . $task . 'GlobalTask.php')) {
                $this->message('Flow global task already exists.', 'error');

                return 1;
            }

            $content_task = $this->getStub(__DIR__ . '/stub/global-task', [
                'task' => $task
            ]);

            $path = base_path('app/Flows/Global');
            if (!$this->isDir($path)) {
                $this->makeDir($path);
            }

            if (!$this->isFile($path . '/' . $task . 'GlobalTask.php')) {
                $this->putFile($path . '/' . $task . 'GlobalTask.php', $content_task);
            }

            $this->message('Flow Global Task <options=bold>[' . $path . '/' . $task . 'GlobalTask.php]</> created successfully.', 'success');
        }

        return 0;
    }
}
