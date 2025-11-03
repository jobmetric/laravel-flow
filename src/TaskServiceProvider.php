<?php

namespace JobMetric\Flow;

use Illuminate\Contracts\Container\BindingResolutionException;
use JobMetric\Flow\Support\TaskRegistry;
use JobMetric\PackageCore\Enums\RegisterClassTypeEnum;
use JobMetric\PackageCore\Exceptions\RegisterClassTypeNotFoundException;
use JobMetric\PackageCore\PackageCore;
use JobMetric\PackageCore\PackageCoreServiceProvider;

/**
 * TaskServiceProvider
 *
 * Registers and loads all Flow tasks automatically.
 * It also provides the TaskRegistry singleton for global access.
 */
class TaskServiceProvider extends PackageCoreServiceProvider
{
    /**
     * Configure the Flow package.
     *
     * @param PackageCore $package
     * @throws RegisterClassTypeNotFoundException
     */
    public function configuration(PackageCore $package): void
    {
        $package
            ->name('laravel-flow')
            ->hasConfig()
            ->registerCommand(\JobMetric\Flow\Commands\MakeTask::class)
            ->registerClass('TaskRegistry', TaskRegistry::class, RegisterClassTypeEnum::SINGLETON->value);
    }

    /**
     * Register all Flow tasks after the package has loaded.
     *
     * @throws BindingResolutionException
     */
    public function afterRegisterPackage(): void
    {
        /** @var TaskRegistry $registry */
        $registry = $this->app->make('TaskRegistry');

        $paths = [
            app_path('Flows/Global'),
            app_path('Flows/Drivers'),
        ];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = glob($path . '/**/*.php');
            foreach ($files as $file) {
                $class = $this->getClassFromPath($file);
                if (class_exists($class)) {
                    $registry->register(new $class());
                }
            }
        }
    }

    /**
     * Convert file path to class name.
     *
     * @param string $file
     * @return string
     */
    protected function getClassFromPath(string $file): string
    {
        $appNamespace = trim(appNamespace(), '\\');
        $relative = str_replace([app_path(), '/', '.php'], ['', '\\', ''], $file);

        return $appNamespace . $relative;
    }
}
