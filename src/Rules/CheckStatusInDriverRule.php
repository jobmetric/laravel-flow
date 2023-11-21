<?php

namespace JobMetric\Flow\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class CheckStatusInDriverRule implements ValidationRule
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
        if (!in_array($value, flowGetStatus($this->driver))) {
            $fail(__('flow::base.validation.check_status_in_driver', ['status' => implode(', ', flowGetStatus($this->driver))]));
        }
    }
}
