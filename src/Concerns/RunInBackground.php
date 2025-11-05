<?php

namespace JobMetric\Flow\Concerns;

trait RunInBackground
{
    /**
     * Indicates that the owning action task prefers to run asynchronously in a background process.
     *
     * @return bool
     */
    public function async(): bool
    {
        return true;
    }
}
