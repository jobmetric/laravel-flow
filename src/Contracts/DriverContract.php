<?php

namespace JobMetric\Flow\Contracts;

use Illuminate\Support\Str;

abstract class DriverContract
{
    protected array $status;

    abstract function handle();

    public function __construct()
    {
        $this->setStatus($this->getStatus());
    }

    /**
     * Get the table associated with the model.
     *
     * @return array
     */
    public function getStatus(): array
    {
        return $this->status ?? [];
    }

    /**
     * Set the status associated with the flow.
     *
     * @param  array $status
     *
     * @return $this
     */
    public function setStatus(array $status): static
    {
        $this->status = $status;

        return $this;
    }
}
