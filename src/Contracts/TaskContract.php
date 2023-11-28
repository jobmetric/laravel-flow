<?php

namespace JobMetric\Flow\Contracts;

abstract class TaskContract
{
    protected string $title;
    protected string $description = "";
    protected array  $fields=[];
    abstract function handle();

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getFields(): array
    {
        return $this->fields;
    }
}
