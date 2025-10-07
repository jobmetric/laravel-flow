<?php

namespace JobMetric\Flow\Http\Requests\FlowState;

use Illuminate\Foundation\Http\FormRequest;
use JobMetric\Flow\Models\FlowState as FlowStateModel;
use JobMetric\Flow\Rules\CheckStatusInDriverRule;
use JobMetric\Language\Facades\Language;
use JobMetric\Translation\Rules\TranslationFieldExistRule;

class StoreFlowStateRequest extends FormRequest
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
     * Normalize incoming data with safe defaults.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public static function normalize(array $data, array $context = []): array
    {
        $data['is_terminal'] = isset($data['is_terminal']) && (bool)$data['is_terminal'];
        $data['config'] = is_array($data['config'] ?? null) ? $data['config'] : [];
        $data['color'] = $data['color'] ?? ($data['is_terminal'] ? config('workflow.state.end.color') : config('workflow.state.middle.color'));
        $data['position'] = $data['position'] ?? [
            'x' => ($data['is_terminal'] ? config('workflow.state.end.position.x') : config('workflow.state.middle.position.x')),
            'y' => ($data['is_terminal'] ? config('workflow.state.end.position.y') : config('workflow.state.middle.position.y')),
        ];

        return $data;
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
        $flowId = (int)($context['flow_id'] ?? $input('flow_id') ?? null);

        $rules = [
            'flow_id' => 'required|integer|exists:flows,id',

            'translation' => 'required|array',

            'status' => [
                'present',
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

        foreach ($locales as $locale) {
            $rules["translation.$locale"] = 'required|array';
            $rules["translation.$locale.name"] = [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($locale, $flowId) {
                    $name = trim((string)$value);

                    if ($name === '') {
                        $fail(trans('workflow::base.validation.flow_state.translation_name_required'));

                        return;
                    }

                    $rule = new TranslationFieldExistRule(FlowStateModel::class, 'name', $locale, null, -1, [
                        'flow_id' => $flowId
                    ], 'workflow::base.fields.name');

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
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        $flowId = (int)($this->context['flow_id'] ?? $this->input('flow_id') ?? null);

        return self::rulesFor($this->all(), [
            'flow_id' => $flowId,
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
            'flow_id' => trans('workflow::base.fields.flow_id'),

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
