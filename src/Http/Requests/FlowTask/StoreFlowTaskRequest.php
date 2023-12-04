<?php

namespace JobMetric\Flow\Http\Requests\Flow;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFlowTaskRequest extends FormRequest
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
            'flow_driver' => 'int|exists:flow_transitions,id',
            'task_driver' => 'string',
            'config' => 'array',
            'order' => 'int',
        ];
    }
}
