<?php

namespace JobMetric\Flow\Http\Requests\Flow;

use Illuminate\Foundation\Http\FormRequest;

class ReorderFlowRequest extends FormRequest
{
    /**
     * Basic field rules (compressed).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ordered_ids'   => 'required|array|min:1',
            'ordered_ids.*' => 'integer',
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
            'ordered_ids' => trans('workflow::base.fields.ordered_ids'),
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
