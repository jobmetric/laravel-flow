<?php

namespace JobMetric\Flow\Http\Requests\FlowTransition;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Flow\Models\FlowTransition;
use JobMetric\Language\Facades\Language;
use JobMetric\Translation\Rules\TranslationFieldExistRule;

class UpdateFlowTransitionRequest extends FormRequest
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
        $flowId = (int)($context['flow_id'] ?? $input['flow_id'] ?? null);
        $flowTransitionId = (int)($context['flow_transition_id'] ?? $input['flow_transition_id'] ?? null);

        $rules = [
            'translation' => 'sometimes|array',

            // if one side comes, the other is required too
            'from' => 'sometimes|integer|exists:' . config('workflow.tables.flow_state') . ',id|required_with:to',
            'to' => 'sometimes|integer|exists:' . config('workflow.tables.flow_state') . ',id|required_with:from',

            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                'unique:' . config('workflow.tables.flow_transition') . ',slug',
            ],
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
                    function ($attribute, $value, $fail) use ($locale, $flowId, $flowTransitionId) {
                        $name = trim((string)$value);

                        if ($name === '') {
                            $fail(trans('workflow::base.validation.flow_transition.translation_name_required'));

                            return;
                        }

                        $rule = new TranslationFieldExistRule(FlowTransition::class, 'name', $locale, $flowTransitionId, -1, [
                            'flow_id' => $flowId
                        ], 'workflow::base.fields.name');

                        if (!$rule->passes($attribute, $name)) {
                            $fail($rule->message());
                        }
                    },
                ];
            }
        }

        return $rules;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        $flowId = (int)($this->context['flow_id'] ?? $this->input('flow_id') ?? null);
        $flowTransitionId = (int)($this->context['flow_transition_id'] ?? $this->input('flow_transition_id') ?? null);

        return self::rulesFor($this->all(), [
            'flow_id' => $flowId,
            'flow_transition_id' => $flowTransitionId,
        ]);
    }

    /**
     * Hook to add cross-field and cross-table validation after base rules pass.
     *
     * - from != to
     * - no duplicate (flow_id, from, to) excluding current record
     * - transition must connect two concrete states (after effective update)
     * - if it's the first transition in the flow, it must start from START
     *
     * @param Validator $validator The validator instance to attach post-validation checks
     *
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $data = $v->getData();

            $flowId = (int)($this->context['flow_id'] ?? $data['flow_id'] ?? null);
            $flowTransitionId = (int)($this->context['flow_transition_id'] ?? $data['flow_transition_id'] ?? null);

            /** @var FlowTransition|null $current */
            $current = $flowTransitionId ? FlowTransition::query()->find($flowTransitionId) : null;

            if ($v->errors()->isNotEmpty() || !$current) {
                return;
            }

            // Effective values after update: if not provided, fall back to current
            $newFrom = array_key_exists('from', $data) ? $data['from'] : $current->from;
            $newTo = array_key_exists('to', $data) ? $data['to'] : $current->to;

            // from and to must not be equal
            if (!is_null($newFrom) && !is_null($newTo) && (int)$newFrom === (int)$newTo) {
                $v->errors()->add('to', trans('workflow::base.validation.flow_transition.from_cannot_equal_to'));
            }

            // duplicate (flow_id, from, to) must not exist (excluding current)
            if (!is_null($newFrom) && !is_null($newTo)) {
                $duplicate = FlowTransition::query()
                    ->where('flow_id', $flowId)
                    ->where('from', (int)$newFrom)
                    ->where('to', (int)$newTo)
                    ->where('id', '!=', $current->id)
                    ->exists();

                if ($duplicate) {
                    $v->errors()->add('to', trans('workflow::base.validation.flow_transition.duplicate_transition'));
                }
            }

            // must connect two concrete states (after effective update)
            if (is_null($newFrom) || is_null($newTo)) {
                $v->errors()->add('from', trans('workflow::base.validation.flow_transition.must_connect_two_states'));
            }

            // if this is the first/only transition in this flow, from must remain START
            $startId = FlowState::query()
                ->where('flow_id', $flowId)
                ->where('type', FlowStateTypeEnum::START())
                ->value('id');

            if (!is_null($newTo) && $startId && (int)$newTo === (int)$startId) {
                $v->errors()->add('to', trans('workflow::base.validation.flow_transition.to_cannot_be_start'));
            }

            $isOnlyTransition = !FlowTransition::query()
                ->where('flow_id', $flowId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($isOnlyTransition && $startId) {
                if ((int)$newFrom !== (int)$startId) {
                    $v->errors()->add('from', trans('workflow::base.validation.flow_transition.first_must_from_start'));
                }
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
            'flow_id' => trans('workflow::base.fields.flow_id'),

            'translation' => trans('workflow::base.fields.translation'),
            'translation.*.name' => trans('workflow::base.fields.name'),

            'from' => trans('workflow::base.fields.from'),
            'to' => trans('workflow::base.fields.to'),
            'slug' => trans('workflow::base.fields.slug'),
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
