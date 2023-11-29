<?php

namespace JobMetric\Flow\Http\Requests\FlowTask;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use JobMetric\Flow\Rules\CheckFlowDriverExistsRule;
use JobMetric\Flow\Rules\CheckFlowTaskExistsRule;

class FlowTaskDetailsRequest extends FormRequest
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
            'driver' => ['required', new CheckFlowDriverExistsRule($this->input('driver'))],
            'task'   => ['required', new CheckFlowTaskExistsRule($this->input('driver'),$this->input('task'))],
        ];
    }
}