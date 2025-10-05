<?php

namespace JobMetric\Flow\Http\Requests\Flow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JobMetric\Flow\Models\Flow as FlowModel;
use JobMetric\Language\Facades\Language;
use JobMetric\Translation\Rules\TranslationFieldExistRule;

class UpdateFlowRequest extends FormRequest
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
     * Build validation rules dynamically for provided fields/locales.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $flowId = (int)($this->context['flow_id'] ?? $this->input('flow_id'));

        $rules = [
            'translation' => 'sometimes|array',

            'subject_type' => 'sometimes|string|max:255',
            'subject_scope' => 'sometimes|nullable|string|max:255',
            'subject_collection' => 'sometimes|nullable|string|max:255',

            'version' => 'sometimes|integer|min:1',
            'is_default' => 'sometimes|boolean',
            'status' => 'sometimes|boolean',

            'active_from' => 'sometimes|nullable|date',
            'active_to' => 'sometimes|nullable|date',

            'channel' => 'sometimes|nullable|string|max:64',
            'ordering' => 'sometimes|integer|min:0',
            'rollout_pct' => 'sometimes|nullable|integer|between:0,100',
            'environment' => 'sometimes|nullable|string|max:64',
        ];

        $input = $this->all();
        $locales = Language::all([
            'status' => true
        ])->pluck('locale')->all();

        if (isset($input['translation']) && is_array($input['translation'])) {
            foreach ($locales as $locale) {
                if (!array_key_exists($locale, $input['translation'])) {
                    continue;
                }

                $rules["translation.$locale"] = 'sometimes|array';
                $rules["translation.$locale.name"] = [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) use ($locale, $flowId) {
                        $name = trim((string)$value);

                        if ($name === '') {
                            $fail(trans('workflow::base.validation.flow_state.translation_name_required'));

                            return;
                        }

                        $rule = new TranslationFieldExistRule(FlowModel::class, 'name', $locale, $flowId, -1, [], 'workflow::base.fields.name');

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
     * Cross-field validation: active_from <= active_to when both provided.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function (Validator $v) {
            $from = $this->input('active_from');
            $to = $this->input('active_to');

            if ($from && $to && strtotime($from) > strtotime($to)) {
                $v->errors()->add('active_from', trans('workflow::base.validation.flow.active_from_before_active_to'));
            }
        });
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

            'subject_type' => trans('workflow::base.fields.subject_type'),
            'subject_scope' => trans('workflow::base.fields.subject_scope'),
            'subject_collection' => trans('workflow::base.fields.subject_collection'),
            'version' => trans('workflow::base.fields.version'),
            'is_default' => trans('workflow::base.fields.is_default'),
            'status' => trans('workflow::base.fields.status'),
            'active_from' => trans('workflow::base.fields.active_from'),
            'active_to' => trans('workflow::base.fields.active_to'),
            'channel' => trans('workflow::base.fields.channel'),
            'ordering' => trans('workflow::base.fields.ordering'),
            'rollout_pct' => trans('workflow::base.fields.rollout_pct'),
            'environment' => trans('workflow::base.fields.environment'),
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
