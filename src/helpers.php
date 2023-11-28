<?php

use Illuminate\Support\Facades\File;
use JobMetric\Flow\Contracts\DriverContract;
use JobMetric\Flow\Facades\Flow;

if(!function_exists('flowResolve')) {
    /**
     * Resolve the given flow instance by name.
     *
     * @param  string  $driver
     *
     * @return DriverContract
     */
    function flowResolve(string $driver): DriverContract
    {
        return Flow::getDriver($driver);
    }
}

if(!function_exists('flowGetStatus')) {
    /**
     * Get the status of the given flow instance by name.
     *
     * @param  string  $driver
     *
     * @return array
     */
    function flowGetStatus(string $driver): array
    {
        return Flow::getStatus($driver);
    }
}

if (!function_exists('resolveClassesFromDirectory')) {
    function resolveClassesFromDirectory(string $namespace=''): array
    {
        $directoryPath = base_path($namespace);
        $phpFiles = File::files($directoryPath, '*.php');
        $objs = [];
        foreach ($phpFiles as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $className = $namespace . '\\' . $className;
            $objs[]=resolve($className);
        }
        return $objs;
    }
}