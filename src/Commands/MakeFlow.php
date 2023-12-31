<?php

namespace JobMetric\Flow\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use JobMetric\PackageCore\Commands\ConsoleTools;

class MakeFlow extends Command
{
    use ConsoleTools;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:flow
                {driver : Flow driver name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Flow Asset';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $driver = $this->argument('driver');
        $driver = Str::studly($driver);

        if ($this->isFile('app/Flows/Drivers/' . $driver . '/' . $driver . 'DriverFlow.php')) {
            $this->message('Flow already exists.', 'error');

            return 1;
        }

        $content_driver = $this->getStub('driver', [
            'driver' => $driver,
        ]);

        $path = base_path('app/Flows/Drivers/' . $driver);
        if (!$this->isDir($path)) {
            $this->makeDir($path);
        }

        if (!$this->isFile($path . '/' . $driver . 'DriverFlow.php')) {
            $this->putFile($path . '/' . $driver . 'DriverFlow.php', $content_driver);
        }

        $this->message('Flow Driver <options=bold>[' . $path . '/' . $driver . 'DriverFlow.php]</> created successfully.', 'success');

        return 0;
    }
}
