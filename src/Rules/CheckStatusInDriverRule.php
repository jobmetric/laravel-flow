<?php

namespace JobMetric\Flow\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Translation\PotentiallyTranslatedString;
use JobMetric\Flow\HasWorkflow;
use JobMetric\Flow\Models\Flow;
use LogicException;
use Throwable;

/**
 * Class CheckStatusInDriverRule
 *
 * Validates that a given status value is allowed for the subject model
 * bound to a Flow (via flow_id). The rule:
 *  - Loads the Flow by id and resolves its subject_type (Eloquent model class).
 *  - Ensures the subject model uses HasWorkflow trait.
 *  - Uses HasWorkflow helpers to detect the status enum values.
 *  - Validates the incoming value exists among those enum values.
 *
 * Error messaging relies on translation keys under `workflow::base.validation.*`.
 */
class CheckStatusInDriverRule implements ValidationRule
{
    /**
     * Flow identifier used to resolve the subject model class.
     *
     * @var int
     */
    private int $flowId;

    /**
     * Create a new rule instance.
     *
     * @param int $flowId The target flow id used to resolve subject_type.
     */
    public function __construct(int $flowId)
    {
        $this->flowId = $flowId;
    }

    /**
     * Run the validation rule.
     *
     * @param string $attribute The validated attribute name.
     * @param mixed $value The provided value to validate.
     * @param Closure(string): PotentiallyTranslatedString $fail Callback to register a failure.
     *
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $flow = Flow::query()->find($this->flowId);
        if (!$flow) {
            $fail(trans('workflow::base.validation.flow_not_found'));

            return;
        }

        $subjectClass = $flow->getAttribute('subject_type');
        if (!is_string($subjectClass) || !class_exists($subjectClass)) {
            $fail(trans('workflow::base.validation.subject_model_invalid'));

            return;
        }

        // Ensure subject model is an Eloquent Model and uses HasWorkflow.
        if (!is_subclass_of($subjectClass, Model::class)) {
            $fail(trans('workflow::base.validation.subject_model_invalid'));

            return;
        }

        if (!$this->usesHasWorkflow($subjectClass)) {
            $fail(trans('workflow::base.validation.model_must_use_has_workflow', [
                'model' => $subjectClass
            ]));

            return;
        }

        /** @var Model&HasWorkflow $subject */
        $subject = new $subjectClass();

        // Attempt to detect allowed enum values from HasWorkflow trait helpers.
        try {
            $allowed = $subject->flowStatusEnumValues();
        } catch (LogicException) {
            // If trait throws due to missing status column, surface a clean message.
            $fail(trans('workflow::base.validation.status_column_missing'));

            return;
        } catch (Throwable) {
            $fail(trans('workflow::base.validation.status_enum_error'));

            return;
        }

        if ($allowed === null || $allowed === []) {
            $fail(trans('workflow::base.validation.status_enum_missing'));

            return;
        }

        // Normalize comparison: accept both strict and stringified matches.
        $allowedStrict = $allowed;
        $allowedString = array_map(static fn($v) => is_bool($v) ? ($v ? '1' : '0') : (string)$v, $allowed);

        $candidateStrict = $value;
        $candidateString = is_bool($value) ? ($value ? '1' : '0') : (string)$value;

        $isValid = in_array($candidateStrict, $allowedStrict, true) || in_array($candidateString, $allowedString, true);

        if (!$isValid) {
            $fail(trans('workflow::base.validation.check_status_in_driver', [
                'status' => implode(', ', $allowedString),
            ]));
        }
    }

    /**
     * Detects whether the given class uses the HasWorkflow trait (recursively).
     *
     * @param class-string $class
     *
     * @return bool
     */
    private function usesHasWorkflow(string $class): bool
    {
        return in_array(HasWorkflow::class, class_uses_recursive($class), true);
    }
}
