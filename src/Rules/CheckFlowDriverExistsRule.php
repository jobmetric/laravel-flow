<?php

namespace JobMetric\Flow\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class CheckFlowDriverExistsRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(
        private readonly string $driver
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
        if (!empty($this->driver) && !is_dir(app_path('Flows\\Drivers\\'.\Str::studly($this->driver)))) {
            $fail(__('flow::base.validation.check_driver_exists', ['driver' => $this->driver]));
        }
    }
}
