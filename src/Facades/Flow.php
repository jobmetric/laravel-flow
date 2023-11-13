<?php

namespace JobMetric\Flow\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @see \JobMetric\Flow\Flow
 */
class Flow extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'Flow';
    }
}
