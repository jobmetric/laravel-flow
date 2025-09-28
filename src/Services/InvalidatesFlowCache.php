<?php

namespace JobMetric\Flow\Services;

/**
 * Trait InvalidatesFlowCache
 *
 * Provides a method to clear caches related to flows.
 */
trait InvalidatesFlowCache
{
    /**
     * Clear caches related to flows.
     *
     * @return void
     */
    protected function forgetCache(): void
    {
        cache()->forget('flows');
        cache()->forget('flow.pick');
    }
}
