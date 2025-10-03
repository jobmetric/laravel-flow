<?php

namespace JobMetric\Flow\Http\Requests\Flow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SetActiveWindowRequest extends FormRequest
{
    /**
     * Basic field rules (compressed).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'active_from' => 'nullable|date',
            'active_to'   => 'nullable|date',
        ];
    }

    /**
     * Cross-field validation: from <= to.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function (Validator $v) {
            $from = $this->input('active_from');
            $to   = $this->input('active_to');

            if ($from && $to && strtotime($from) > strtotime($to)) {
                $v->errors()->add('active_from', trans('workflow::base.validation.flow.active_from_before_active_to'));
            }
        });
    }

    /**
     * Attributes via language keys.
     *
     * @return array<string,string>
     */
    public function attributes(): array
    {
        return [
            'active_from' => trans('workflow::base.fields.active_from'),
            'active_to'   => trans('workflow::base.fields.active_to'),
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
