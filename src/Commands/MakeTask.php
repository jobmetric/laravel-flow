<?php

namespace JobMetric\Flow\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use JobMetric\Flow\HasWorkflow;
use JobMetric\PackageCore\Commands\ConsoleTools;

class MakeTask extends Command
{
    use ConsoleTools;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flow:make-task
        {type? : The type of the task (e.g. validation, restriction, action) default: action}
        {name? : The name of the task (e.g. RestrictOrderCancellation)}
        {model? : Driver name (e.g. Order)}
        {title? : The title of the task (e.g. translation key flow::base.task.restrict_order_cancellation.title)}
        {--f|force : Overwrite if file already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Flow Task class.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $appNamespace = trim(appNamespace(), "\\");

        // select task type
        $type = $this->argument('type');
        if (! $type) {
            $type = $this->choice('Enter the type of the task:', ['Validation', 'Restriction', 'Action'], 2);

            $this->line("Selected task type: <options=bold>[$type]</>");
        }

        if (! in_array($type, ['Validation', 'Restriction', 'Action'], true)) {
            $this->message("Invalid task type: [{$type}]. Allowed types are: Validation, Restriction, Action.", 'error');

            return self::FAILURE;
        }

        // ask for task name
        $name = $this->argument('name');
        if (! $name) {
            askAgainTaskName:
            $name = $this->ask('Enter the name of the task (e.g. RestrictOrderCancellation)');

            if (! $name) {
                $this->message("Task name is required.", 'error');

                goto askAgainTaskName;
            }
            else {
                $this->line("Task name: <options=bold>[{$name}]</>");
            }
        }

        $name = Str::studly($name);

        // ask for model
        $model = $this->argument('model');
        if (! $model) {
            $model = $this->choice('Enter the namespace model:', $this->getEloquentModels(), 0);
        }

        if (in_array(HasWorkflow::class, class_uses_recursive($model), true)) {
            $driver = class_basename($model);
        }
        else {
            $this->message("The model [{$model}] does not use the HasWorkflow trait.", 'error');

            return self::FAILURE;
        }

        // ask for title
        $title = $this->argument('title');
        if (! $title) {
            askAgainTaskTitle:
            $title = $this->ask('Enter the title of the task (e.g. translation key flow::base.task.restrict_order_cancellation.title)');

            if (! $title) {
                $this->message("Task title is required.", 'error');

                goto askAgainTaskTitle;
            }
        }

        $force = $this->option('force');

        $ns = $appNamespace . '\\Flows\\' . $driver;

        $targetDir = app_path('Flows' . DIRECTORY_SEPARATOR . $driver);
        $classTarget = $targetDir . DIRECTORY_SEPARATOR . $name . $type . 'Task.php';

        // stub contents
        $classContent = $this->getStub(__DIR__ . "/stub/" . strtolower($type), [
            'namespace' => $ns,
            'task'      => $name . $type,
            'model'     => '\\' . $model . '::class',
            'title'     => $title,
        ]);

        // create custom field directory
        if (! $this->isDir($targetDir)) {
            $this->makeDir($targetDir);
        }

        if ($this->isFile($classTarget) && ! $force) {
            $this->message("Flow $type Task class already exists: <options=bold>[$ns]</>, Use --force to overwrite.", 'error');

            // confirm overwrite
            if (! $this->confirm('Do you want to overwrite the existing file?')) {
                $this->line('Operation cancelled.');

                return self::FAILURE;
            }
        }

        $this->putFile($classTarget, $classContent);

        $this->message("Flow $type Task <options=bold>[$ns\\$name{$type}Task]</> created successfully.", "success");

        // tips for registration
        $this->line('');
        $this->info('Next steps:');
        $this->line('- Register your new flow task in a service provider using FlowTaskRegistry.');
        $this->line("  Example: app('FlowTaskRegistry')->register(new \\$ns\\$name{$type}Task);");

        return self::SUCCESS;
    }

    private function getEloquentModels(): array
    {
        return [
            \App\Models\Order::class,
            \App\Models\User::class,
            \App\Models\Product::class,
        ];
    }
}
