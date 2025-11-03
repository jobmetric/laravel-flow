<?php

namespace JobMetric\Flow\Abstracts;

use Illuminate\Database\Eloquent\Model;
use JobMetric\Flow\Enums\TaskOperationType;
use JobMetric\Form\FormBuilder;

abstract class TaskAbstract
{
    /**
     * Model class the task applies to
     *
     * @var Model
     */
    public Model $model;

    /**
     * Type of task
     *
     * @var TaskOperationType
     */
    public TaskOperationType $type;

    /**
     * title of task
     *
     * @var string
     */
    public string $title;

    /**
     * description of task
     *
     * @var ?string
     */
    public ?string $description = null;

    /**
     * Run the task asynchronously
     *
     * @var bool
     */
    public bool $async = false;

    /**
     * Form of the task
     *
     * @return FormBuilder
     */
    abstract public function form(): FormBuilder;

    /**
     * Handle the task logic
     *
     * @param array $config Configuration array for the task
     *
     * @return mixed
     */
    abstract public function handle(array $config): mixed;
}
