<?php

namespace JobMetric\Flow\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class CheckFlowTaskExistsRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(
        private  string $driver
        ,private readonly string $task
    )
    {
    }

    /**
     * Run the validation rule.
     *
     * @param Closure(string): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $sep=DIRECTORY_SEPARATOR;
        $this->driver=\Str::studly($this->driver);
        if (strtolower($this->driver)=='global') {
            $directory="App{$sep}Flows{$sep}Global";
        }
        else{
            $directory="App{$sep}Flows{$sep}Drivers{$sep}$this->driver{$sep}Tasks";
        }
        $taskPath=$directory.$sep.\Str::studly($this->task);
        if (!is_file($taskPath)){
            $fail(__('flow: base.validation.task_driver_not_found',['task'=>$this->task]));
        }
    }
}
