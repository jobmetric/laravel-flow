<?php

namespace JobMetric\Flow;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Throwable;

trait FactoryHelper
{
    /**
     * merge factory
     *
     * @return void
     */
    public function factoryResolver(): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            $namespace = 'Database\\Factories\\';
            $appScope = $this->appNamespace();

            if (Str::startsWith($modelName, $appScope . 'Models\\')) {
                $modelName = Str::after($modelName, $appScope . 'Models\\');
            } else {
                $array = explode('\\', $modelName);

                if (count($array) > 2) {
                    $appScope = $array[0] . '\\' . $array[1] . '\\';
                    $namespace = $appScope . 'Factories\\';

                    $modelName = Str::after($modelName, $appScope . 'Models\\');
                } else {
                    $modelName = Str::after($modelName, $appScope);
                }
            }

            return $namespace . $modelName . 'Factory';
        });
    }

    /**
     * Get the application namespace for the application.
     *
     * @return string
     */
    private function appNamespace(): string
    {
        try {
            return Container::getInstance()
                ->make(Application::class)
                ->getNamespace();
        } catch (Throwable) {
            return 'App\\';
        }
    }
}
