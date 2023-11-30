<?php

namespace JobMetric\Flow\Contracts;

use Illuminate\Support\Str;

abstract class DriverContract
{
    /**
     * title of driver
     *
     * validation: required
     * @var string
     */
    protected string $title = "";

    /**
     * status of driver
     *
     * validation: optional
     * @var array
     */
    protected array $status;

    abstract function handle();

    public function __construct()
    {
        $this->setStatus($this->getStatus());
    }

    /**
     * Get the title associated with the driver.
     *
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title ?? null;
    }

    /**
     * Get the status associated with the driver.
     *
     * @return array
     */
    public function getStatus(): array
    {
        return $this->status ?? [];
    }

    /**
     * Set the status associated with the driver.
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
