<?php

namespace JobMetric\Flow\Http\Requests\FlowState;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Flow\Facades\Flow as FlowFacade;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Rules\CheckStatusInDriverRule;

class StoreFlowStateRequest extends FormRequest
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
        /** @var Flow $flow */
        $flow = $parameters['flow'];

        return [
            'type' => 'in:' . implode(',', FlowStateTypeEnum::values()),
            'color' => 'sometimes|string',
            'position' => 'nullable|array',
            'position.x' => 'required_with:position|numeric',
            'position.y' => 'required_with:position|numeric',
            'status' => [
                'required',
                new CheckStatusInDriverRule($flow->driver)
            ]
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'color' => $this->color ?? '#ddd',
            'position' => $this->position ?? ['x' => 0, 'y' => 0],
        ]);
    }
}
