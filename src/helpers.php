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
        if (!is_dir($directoryPath)){
            throw new \Illuminate\Contracts\Filesystem\FileNotFoundException();
        }
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

if (!function_exists('resolveClassfromDirectory')) {
    function resolveClassFromDirectory(string $namespace,string $className){
        $directoryPath = base_path($namespace);
        if (!is_dir($directoryPath)){
            throw new \Symfony\Component\Finder\Exception\DirectoryNotFoundException(code:404);
        }
        $className=Str::studly($className);
        $file=$directoryPath.DIRECTORY_SEPARATOR.$className;
        if (!is_file($file.'.php')){
            throw new \Illuminate\Contracts\Filesystem\FileNotFoundException(); 
        }
        return resolve($file);
    }
}

