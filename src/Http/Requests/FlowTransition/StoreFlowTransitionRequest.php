<?php

namespace JobMetric\Flow\Http\Requests\FlowTransition;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFlowTransitionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'from' => 'nullable|exists:'.config('workflow.tables.flow_state').',id',
            'to' => 'nullable|exists:'.config('workflow.tables.flow_state').',id',
            'slug' => 'nullable|string',
        ];
    }
}
