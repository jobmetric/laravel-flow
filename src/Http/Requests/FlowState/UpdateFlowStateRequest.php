<?php

namespace JobMetric\Flow\Http\Requests\FlowState;

use Illuminate\Foundation\Http\FormRequest;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Flow\Rules\CheckStatusInDriverRule;
use JobMetric\Language\Facades\Language;
use JobMetric\Translation\Rules\TranslationFieldExistRule;

class UpdateFlowStateRequest extends FormRequest
{
    /**
     * External context (injected via dto()).
     *
     * @var array<string,mixed>
     */
    protected array $context = [];

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * Build validation rules dynamically for active locales and scalar fields.
     *
     * @param array<string,mixed> $input
     * @param array<string,mixed> $context
     *
     * @return array<string,mixed>
     */
    public static function rulesFor(array $input, array $context = []): array
    {
        $flowId = (int)($context['flow_id'] ?? $input['flow_id'] ?? 0);
        $stateId = (int)($context['state_id'] ?? $input['state_id'] ?? 0);

        $rules = [
            'translation' => 'sometimes|array',

            'status' => [
                'sometimes',
                'nullable',
                new CheckStatusInDriverRule($flowId),
            ],

            'config' => 'sometimes|array',

            'color' => 'sometimes|nullable|hex_color',
            'icon' => 'sometimes|nullable|string',
            'position' => 'sometimes|array|required_array_keys:x,y',
            'position.x' => 'numeric',
            'position.y' => 'numeric',
            'is_terminal' => 'sometimes|boolean',
        ];

        $locales = Language::all([
            'status' => true
        ])->pluck('locale')->all();

        if (!empty($input['translation']) && is_array($input['translation'])) {
            foreach ($locales as $locale) {
                if (!array_key_exists($locale, $input['translation'])) {
                    continue;
                }

                $rules["translation.$locale"] = 'sometimes|array';
                $rules["translation.$locale.name"] = [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) use ($locale, $flowId, $stateId) {
                        $name = trim((string)$value);

                        if ($name === '') {
                            $fail(trans('workflow::base.validation.flow_state.translation_name_required'));

                            return;
                        }

                        $rule = new TranslationFieldExistRule(FlowState::class, 'name', $locale, $stateId, -1, [
                            'flow_id' => $flowId
                        ], 'workflow::base.fields.name');

                        if (!$rule->passes($attribute, $name)) {
                            $fail($rule->message());
                        }
                    },
                ];

                $rules["translation.$locale.description"] = 'nullable|string';
            }
        }

        return $rules;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        $flowId = (int)($this->context['flow_id'] ?? $this->input('flow_id'));
        $stateId = (int)($this->context['state_id'] ?? $this->input('state_id'));

        return self::rulesFor($this->all(), [
            'flow_id' => $flowId,
            'state_id' => $stateId,
        ]);
    }

    /**
     * Attributes via language keys.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'translation' => trans('workflow::base.fields.translation'),
            'translation.*.name' => trans('workflow::base.fields.name'),
            'translation.*.description' => trans('workflow::base.fields.description'),

            'status' => trans('workflow::base.fields.status'),
            'color' => trans('workflow::base.fields.color'),
            'position' => trans('workflow::base.fields.position'),
            'position.x' => trans('workflow::base.fields.position_x'),
            'position.y' => trans('workflow::base.fields.position_y'),
            'is_terminal' => trans('workflow::base.fields.is_terminal'),
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
