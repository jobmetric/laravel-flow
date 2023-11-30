<?php

namespace JobMetric\Flow\Contracts;

abstract class TaskContract
{
    /**
     * title of task
     *
     * validation: required
     * @var string
     */
    protected string $title;

    /**
     * description of task
     *
     * validation: optional
     * @var ?string
     */
    protected ?string $description = null;

    /**
     * fields of task
     *
     * validation: optional
     * sample: [
     *     [
     *          'type' => 'input',
     *          'key' => 'name',
     *          'value' => null,
     *          'placeholder' => 'name',
     *          'options' => [
     *              [
     *                  'key' => 'name',
     *                  'value' => 'name'
     *              ],
     *              ...
     *          ],
     *          'validation' => [
     *              'required',
     *          ],
     *          ...
     *     ]
     * ]
     *
     * @var array
     */
    protected array  $fields=[];

    abstract function handle();

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getFields(): array
    {
        return $this->fields;
    }
}
