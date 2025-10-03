<?php

namespace JobMetric\Flow\Http\Requests\Flow;

use Illuminate\Foundation\Http\FormRequest;

class SetRolloutRequest extends FormRequest
{
    /**
     * Basic field rules (compressed).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rollout_pct' => 'nullable|integer|between:0,100',
        ];
    }

    /**
     * Attributes via language keys.
     *
     * @return array<string,string>
     */
    public function attributes(): array
    {
        return [
            'rollout_pct' => trans('workflow::base.fields.rollout_pct'),
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
