<?php

use JobMetric\Flow\Contracts\DriverContract;
use JobMetric\Flow\Facades\Flow;

if (!function_exists('flowResolve')) {
    /**
     * Resolve the given flow instance by name.
     *
     * @param string $driver
     *
     * @return DriverContract
     */
    function flowResolve(string $driver): DriverContract
    {
        return Flow::getDriver($driver);
    }
}

if (!function_exists('flowGetStatus')) {
    /**
     * Get the status of the given flow instance by name.
     *
     * @param string $driver
     *
     * @return array
     */
    function flowGetStatus(string $driver): array
    {
        return Flow::getStatus($driver);
    }
}

