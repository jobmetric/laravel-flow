<?php

namespace JobMetric\Flow\Http\Requests\FlowTransition;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Flow\Models\FlowTransition;
use JobMetric\Language\Facades\Language;
use JobMetric\Translation\Rules\TranslationFieldExistRule;

class StoreFlowTransitionRequest extends FormRequest
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
        $flowId = (int)($context['flow_id'] ?? ($input['flow_id'] ?? null));

        $rules = [
            'flow_id' => 'required|integer|exists:flows,id',

            'translation' => 'required|array',

            'from' => 'required|integer|exists:' . config('workflow.tables.flow_state') . ',id',
            'to' => 'required|integer|exists:' . config('workflow.tables.flow_state') . ',id',
            'slug' => [
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

        foreach ($locales as $locale) {
            $rules["translation.$locale"] = 'required|array';
            $rules["translation.$locale.name"] = [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($locale, $flowId) {
                    $name = trim((string)$value);

                    if ($name === '') {
                        $fail(trans('workflow::base.validation.flow_transition.translation_name_required'));

                        return;
                    }

                    $rule = new TranslationFieldExistRule(FlowTransition::class, 'name', $locale, null, -1, [
                        'flow_id' => $flowId
                    ], 'workflow::base.fields.name');

                    if (!$rule->passes($attribute, $name)) {
                        $fail($rule->message());
                    }
                },
            ];
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
     * Hook to add cross-field and cross-table validation after base rules pass.
     *
     * Enforces:
     * - from != to
     * - to must not be START state
     * - duplicate (flow_id, from, to) must not exist
     * - if this is the first transition in the flow, it must start from START
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

            // If base rules failed, skip heavy checks
            if ($v->errors()->isNotEmpty() || is_null($flowId)) {
                return;
            }


            $from = $data['from'] ?? null;
            $to = $data['to'] ?? null;

            // Load START state id for this flow (if any)
            $startId = FlowState::query()
                ->where('flow_id', $flowId)
                ->where('type', FlowStateTypeEnum::START())
                ->value('id');

            // from and to must not be equal
            if (!is_null($from) && !is_null($to) && (int)$from === (int)$to) {
                $v->errors()->add('to', trans('workflow::base.validation.flow_transition.from_cannot_equal_to'));
            }

            // to must not point to START
            if (!is_null($to) && $startId && (int)$to === (int)$startId) {
                $v->errors()->add('to', trans('workflow::base.validation.flow_transition.to_cannot_be_start'));
            }

            // (flow_id, from, to) must be unique
            if (!is_null($from) && !is_null($to)) {
                $duplicate = FlowTransition::query()
                    ->where('flow_id', $flowId)
                    ->where('from', (int)$from)
                    ->where('to', (int)$to)
                    ->exists();

                if ($duplicate) {
                    $v->errors()->add('to', trans('workflow::base.validation.flow_transition.duplicate_transition'));
                }
            }

            // If this is the first transition of the flow, it must start from START
            $hasAny = FlowTransition::query()
                ->where('flow_id', $flowId)
                ->exists();

            if (!$hasAny) {
                // first transition in this flow
                if (is_null($from) || (int)$from !== (int)$startId) {
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

    public function authorize(): bool
    {
        return true;
    }
}
