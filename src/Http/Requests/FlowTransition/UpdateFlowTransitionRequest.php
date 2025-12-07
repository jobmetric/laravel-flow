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
        $flowId = (int) ($context['flow_id'] ?? $input['flow_id'] ?? null);
        $flowTransitionId = (int) ($context['flow_transition_id'] ?? $input['flow_transition_id'] ?? null);

        $rules = [
            'translation' => 'sometimes|array',

            // from and to can be nullable for generic transitions
            // but at least one must be set (enforced in withValidator)
            'from'        => 'sometimes|nullable|integer|exists:' . config('workflow.tables.flow_state') . ',id',
            'to'          => 'sometimes|nullable|integer|exists:' . config('workflow.tables.flow_state') . ',id',

            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                'unique:' . config('workflow.tables.flow_transition') . ',slug',
            ],
        ];

        $locales = Language::getActiveLocales();

        if (! empty($input['translation']) && is_array($input['translation'])) {
            foreach ($locales as $locale) {
                if (! array_key_exists($locale, $input['translation'])) {
                    continue;
                }

                $rules["translation.$locale"] = 'sometimes|array';
                $rules["translation.$locale.name"] = [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) use ($locale, $flowId, $flowTransitionId) {
                        $name = trim((string) $value);

                        if ($name === '') {
                            $fail(trans('workflow::base.validation.flow_transition.translation_name_required'));

                            return;
                        }

                        $rule = new TranslationFieldExistRule(FlowTransition::class, 'name', $locale, $flowTransitionId, -1, [
                            'flow_id' => $flowId,
                        ], 'workflow::base.fields.name');

                        if (! $rule->passes($attribute, $name)) {
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
        $flowId = (int) ($this->context['flow_id'] ?? $this->input('flow_id') ?? null);
        $flowTransitionId = (int) ($this->context['flow_transition_id'] ?? $this->input('flow_transition_id') ?? null);

        return self::rulesFor($this->all(), [
            'flow_id'            => $flowId,
            'flow_transition_id' => $flowTransitionId,
        ]);
    }

    /**
     * Hook to add cross-field and cross-table validation after base rules pass.
     *
     * - at least one of from or to must be set
     * - self-loop (from == to) is allowed except for start states
     * - no duplicate (flow_id, from, to) excluding current record
     * - to must not point to START state
     * - if it's the first/only transition in the flow, it must start from START
     * - from START state, only one transition can exit
     * - to terminal state (is_terminal), generic output transitions are not allowed
     *
     * @param Validator $validator The validator instance to attach post-validation checks
     *
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $data = $v->getData();

            $flowId = (int) ($this->context['flow_id'] ?? $data['flow_id'] ?? null);
            $flowTransitionId = (int) ($this->context['flow_transition_id'] ?? $data['flow_transition_id'] ?? null);

            /** @var FlowTransition|null $current */
            $current = $flowTransitionId ? FlowTransition::query()->find($flowTransitionId) : null;

            if ($v->errors()->isNotEmpty() || ! $current) {
                return;
            }

            // Effective values after update: if not provided, fall back to current
            $newFrom = array_key_exists('from', $data) ? $data['from'] : $current->from;
            $newTo = array_key_exists('to', $data) ? $data['to'] : $current->to;

            // At least one of from or to must be set
            if (is_null($newFrom) && is_null($newTo)) {
                $v->errors()->add('from', trans('workflow::base.validation.flow_transition.at_least_one_required'));

                return;
            }

            // Load START state id for this flow (if any)
            $startId = FlowState::query()
                ->where('flow_id', $flowId)
                ->where('type', FlowStateTypeEnum::START())
                ->value('id');

            // Self-loop is allowed, but not for start states
            if (! is_null($newFrom) && ! is_null($newTo) && (int) $newFrom === (int) $newTo) {
                if ($startId && (int) $newFrom === (int) $startId) {
                    $v->errors()
                        ->add('to', trans('workflow::base.validation.flow_transition.start_state_cannot_self_loop'));
                }
            }

            // to must not point to START
            if (! is_null($newTo) && $startId && (int) $newTo === (int) $startId) {
                $v->errors()->add('to', trans('workflow::base.validation.flow_transition.to_cannot_be_start'));
            }

            // Terminal states cannot have generic output transitions (to = null)
            // Check if from state is terminal when creating a generic output transition
            if (! is_null($newFrom) && is_null($newTo)) {
                $fromState = FlowState::query()->where('flow_id', $flowId)->where('id', (int) $newFrom)->first();

                if ($fromState && $fromState->is_end) {
                    $v->errors()
                        ->add('to', trans('workflow::base.validation.flow_transition.terminal_state_no_generic_output'));
                }
            }

            // duplicate (flow_id, from, to) must not exist (excluding current, including null values)
            $duplicate = FlowTransition::query()
                ->where('flow_id', $flowId)
                ->where('id', '!=', $current->id)
                ->where(function ($q) use ($newFrom) {
                    if (is_null($newFrom)) {
                        $q->whereNull('from');
                    }
                    else {
                        $q->where('from', (int) $newFrom);
                    }
                })
                ->where(function ($q) use ($newTo) {
                    if (is_null($newTo)) {
                        $q->whereNull('to');
                    }
                    else {
                        $q->where('to', (int) $newTo);
                    }
                })
                ->exists();

            if ($duplicate) {
                $v->errors()->add('to', trans('workflow::base.validation.flow_transition.duplicate_transition'));
            }

            // if this is the first/only transition in this flow, from must remain START
            $isOnlyTransition = ! FlowTransition::query()
                ->where('flow_id', $flowId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($isOnlyTransition && $startId) {
                if (is_null($newFrom) || (int) $newFrom !== (int) $startId) {
                    $v->errors()->add('from', trans('workflow::base.validation.flow_transition.first_must_from_start'));
                }
            }

            // From START state, only one transition can exit
            if (! is_null($newFrom) && $startId && (int) $newFrom === (int) $startId) {
                $hasStartTransition = FlowTransition::query()
                    ->where('flow_id', $flowId)
                    ->where('from', (int) $startId)
                    ->where('id', '!=', $current->id)
                    ->exists();

                if ($hasStartTransition) {
                    $v->errors()
                        ->add('from', trans('workflow::base.validation.flow_transition.start_state_only_one_transition'));
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

            'translation'        => trans('workflow::base.fields.translation'),
            'translation.*.name' => trans('workflow::base.fields.name'),

            'from' => trans('workflow::base.fields.from'),
            'to'   => trans('workflow::base.fields.to'),
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
