<?php

namespace JobMetric\Flow\Http\Requests\Flow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JobMetric\Flow\Models\Flow as FlowModel;
use JobMetric\Language\Facades\Language;
use JobMetric\Translation\Rules\TranslationFieldExistRule;

class StoreFlowRequest extends FormRequest
{
    /**
     * Build validation rules dynamically for active locales and scalar fields.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'translation' => 'required|array',

            'subject_type' => 'required|string|max:255',
            'subject_scope' => 'nullable|string|max:255',
            'subject_collection' => 'nullable|string|max:255',

            'version' => 'sometimes|integer|min:1',
            'is_default' => 'sometimes|boolean',
            'status' => 'sometimes|boolean',

            'active_from' => 'nullable|date',
            'active_to' => 'nullable|date',

            'channel' => 'nullable|string|max:64',
            'ordering' => 'sometimes|integer|min:0',
            'rollout_pct' => 'nullable|integer|between:0,100',
            'environment' => 'nullable|string|max:64',
        ];

        // Active locales
        $locales = Language::all([
            'status' => true
        ])->pluck('locale')->all();

        foreach ($locales as $locale) {
            $rules["translation.$locale"] = 'required|array';
            $rules["translation.$locale.name"] = [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($locale) {
                    $name = trim((string)$value);

                    if ($name === '') {
                        $fail(trans('workflow::base.validation.flow.translation_name_required'));

                        return;
                    }

                    $rule = new TranslationFieldExistRule(FlowModel::class, 'name', $locale, null, -1, [], 'workflow::base.fields.name');

                    if (!$rule->passes($attribute, $name)) {
                        $fail($rule->message());
                    }
                },
            ];
            $rules["translation.$locale.description"] = 'nullable|string';
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
