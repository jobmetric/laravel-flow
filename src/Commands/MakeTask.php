<?php

namespace JobMetric\Flow\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JobMetric\PackageCore\Commands\ConsoleTools;

/**
 * Class MakeTask
 *
 * This Artisan command generates a new Flow Task class within the `app/Flows` directory.
 * You can create a global task or a driver-specific one (e.g., Order, Product, etc.).
 * The command uses a stub file to scaffold a task class that extends the base Flow TaskContract.
 *
 * ## Usage examples:
 * ```
 * php artisan make:flow-task CheckUserStatus
 * php artisan make:flow-task CheckUserStatus Order
 * ```
 *
 * ## Options:
 * - `--force` or `-f`: Overwrite the file if it already exists.
 *
 * The generated file will be created in one of the following directories:
 * - Global: `app/Flows/Global/CheckUserStatusGlobalTask.php`
 * - Driver: `app/Flows/Drivers/Order/Tasks/CheckUserStatusOrderTask.php`
 *
 * After generation, you should register the task in `TaskRegistry` for automatic discovery.
 *
 * @package JobMetric\Flow\Commands
 */
class MakeTask extends Command
{
    use ConsoleTools;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:flow-task
        {name : The name of the task (e.g. RestrictOrderCancellation)}
        {driver? : Optional driver name (e.g. Order)}
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
        $name = Str::studly($this->argument('name'));
        $driver = $this->argument('driver') ? Str::studly($this->argument('driver')) : null;
        $force = $this->option('force');

        // Define base directory for the new task file
        $basePath = $driver
            ? base_path("app/Flows/Drivers/{$driver}/Tasks")
            : base_path("app/Flows/Global");

        File::ensureDirectoryExists($basePath);

        $className = $driver ? "{$name}{$driver}Task" : "{$name}GlobalTask";
        $filePath = "{$basePath}/{$className}.php";

        if (File::exists($filePath) && !$force) {
            $this->message("Flow Task already exists: <options=bold>[{$filePath}]</>", 'error');
            return self::FAILURE;
        }

        // Locate stub file
        $stubPath = __DIR__ . '/stub/global-task.php.stub';

        if (!File::exists($stubPath)) {
            $this->message("Stub file not found at: {$stubPath}", 'error');
            return self::FAILURE;
        }

        $stub = File::get($stubPath);

        // Replace variables in stub
        $namespace = $driver
            ? "App\\Flows\\Drivers\\{$driver}\\Tasks"
            : "App\\Flows\\Global";

        $content = str_replace(
            ['{{namespace}}', '{{task}}'],
            [$namespace, $name],
            $stub
        );

        File::put($filePath, $content);

        $this->message("Flow Task created successfully: <options=bold>[{$filePath}]</>", 'success');

        $this->line('');
        $this->info('Next steps:');
        $this->line("- Register it in TaskRegistry for auto-loading:");
        $this->line("  Example: app('TaskRegistry')->register(new \\{$namespace}\\{$className}());");

        return self::SUCCESS;
    }
}
