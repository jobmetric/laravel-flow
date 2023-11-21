<?php

namespace JobMetric\Flow\Http\Requests\FlowState;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use JobMetric\Flow\Enums\TableFlowStateFieldTypeEnum;
use JobMetric\Flow\Facades\FlowState;
use JobMetric\Flow\Rules\CheckStatusInDriverRule;

class UpdateFlowStateRequest extends FormRequest
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
        $parameters = request()->route()->parameters();
        $flow_state = FlowState::show($parameters['flow_state'], ['flow']);

        return [
            'type' => [
                'sometimes',
                Rule::in(array_diff(TableFlowStateFieldTypeEnum::values(), [TableFlowStateFieldTypeEnum::START()])),
            ],
            'color' => 'sometimes|string',
            'position' => 'nullable|array',
            'position.x' => 'required_with:position|numeric',
            'position.y' => 'required_with:position|numeric',
            'status' => [
                'sometimes',
                new CheckStatusInDriverRule($flow_state->flow->driver)
            ]
        ];
    }
}
