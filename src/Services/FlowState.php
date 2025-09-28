<?php

namespace JobMetric\Flow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Flow\Http\Resources\FlowStateResource;
use JobMetric\Flow\Models\FlowState as FlowStateModel;
use JobMetric\Flow\Models\FlowTransition;
use JobMetric\Language\Facades\Language;
use JobMetric\PackageCore\Output\Response;
use JobMetric\PackageCore\Services\AbstractCrudService;
use JobMetric\Translation\Rules\TranslationFieldExistRule;
use Throwable;

/**
 * Class FlowState
 *
 * Minimal CRUD service for FlowState with all business rules enforced inside
 * doStore, doUpdate, and doDestroy.
 */
class FlowState extends AbstractCrudService
{
    use InvalidatesFlowCache;

    /**
     * Translation key for entity name used in responses.
     *
     * @var string
     */
    protected string $entityName = 'workflow::base.entity_names.flow_state';

    /**
     * Bound Eloquent model.
     *
     * @var class-string
     */
    protected static string $modelClass = FlowStateModel::class;

    /**
     * Bound API Resource.
     *
     * @var class-string
     */
    protected static string $resourceClass = FlowStateResource::class;

    /**
     * Allowed fields for query builder (select/filter/sort).
     *
     * @var string[]
     */
    protected static array $fields = [
        'id',
        'flow_id',
        'type',
        'config',
        'status',
        'created_at',
        'updated_at',
    ];

    /**
     * Create a state with validations and config normalization.
     *
     * @param array $data
     * @param array $with
     *
     * @return Response
     * @throws Throwable
     */
    public function doStore(array $data, array $with = []): Response
    {
        if (empty($data['flow_id'])) {
            throw ValidationException::withMessages([
                'flow_id' => [
                    trans('workflow::base.validation.flow_state.flow_id_required')
                ],
            ]);
        }

        if (empty($data['translation']) || !is_array($data['translation'])) {
            throw ValidationException::withMessages([
                'translation' => [
                    trans('workflow::base.validation.flow_state.translation_name_required')
                ],
            ]);
        }

        $data['type'] = FlowStateTypeEnum::STATE();

        // Get active languages
        $locales = Language::all([
            'status' => true
        ])->pluck('locale')->all();

        $normalized = [];
        foreach ($locales as $locale) {
            $fields = (array)($data['translation'][$locale] ?? []);

            $name = trim((string)($fields['name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages([
                    'translation' => [
                        trans('workflow::base.validation.flow_state.translation_name_required')
                    ],
                ]);
            }

            // Unique per flow_id + locale on "name"
            $uniqueRule = new TranslationFieldExistRule(
                FlowStateModel::class,
                'name',
                $locale,
                null,
                -1,
                ['flow_id' => (int)$data['flow_id']],
                'workflow::base.fields.name'
            );
            if (!$uniqueRule->passes("translation.$locale.name", $name)) {
                throw ValidationException::withMessages([
                    "translation.$locale.name" => [$uniqueRule->message()],
                ]);
            }

            $normalized[$locale] = [
                'name' => $name,
                'description' => $fields['description'] ?? null,
            ];
        }

        $data['translation'] = $normalized;

        // Pull is_terminal from request and ensure it's always present in config
        $isTerminal = (bool)($data['is_terminal'] ?? false);
        unset($data['is_terminal']);

        $defaults = [
            'is_terminal' => false,
            'color' => config('flow.state.default.color', '#fff'),
            'icon' => config('flow.state.default.icon', 'circle'),
            'position' => [
                'x' => 0,
                'y' => 0
            ],
        ];

        $config = is_array($data['config'] ?? null) ? $data['config'] : [];
        Arr::set($config, 'is_terminal', $isTerminal);

        $data['config'] = array_replace_recursive($defaults, $config);

        if (!array_key_exists('is_terminal', $data['config'])) {
            $data['config']['is_terminal'] = false;
        }

        return parent::store($data, $with);
    }

    /**
     * Update a state with validations and config normalization.
     *
     * @param int $id
     * @param array $data
     * @param array $with
     *
     * @return Response
     * @throws Throwable
     */
    public function doUpdate(int $id, array $data, array $with = []): Response
    {
        /** @var FlowStateModel $state */
        $state = FlowStateModel::query()->findOrFail($id);

        // Prevent deactivation when any connected transition exists
        if (array_key_exists('status', $data) && (int)$state->getAttribute('status') === 1 && (int)$data['status'] === 0) {
            $hasConnected = FlowTransition::query()
                ->where(function ($query) use ($id) {
                    $query->where('from', $id)->orWhere('to', $id);
                })
                ->exists();

            if ($hasConnected) {
                throw ValidationException::withMessages([
                    'status' => [
                        trans('workflow::base.validation.flow_state.cannot_deactivate_connected')
                    ],
                ]);
            }
        }

        // If translation provided, accept only active locales; require non-empty name for provided ones
        if (array_key_exists('translation', $data) && is_array($data['translation'])) {
            $incoming = $data['translation'];

            $locales = Language::all([
                'status' => true
            ])->pluck('locale')->all();

            $normalized = [];
            foreach ($locales as $locale) {
                if (!array_key_exists($locale, $incoming)) {
                    continue;
                }

                $fields = (array)$incoming[$locale];
                $name = trim((string)($fields['name'] ?? ''));
                if ($name === '') {
                    throw ValidationException::withMessages([
                        'translation' => [
                            trans('workflow::base.validation.flow_state.translation_name_required')
                        ],
                    ]);
                }

                // Unique per flow_id + locale on "name" (exclude current state)
                $flowIdForRule = (int)($data['flow_id'] ?? $state->flow_id);
                $uniqueRule = new TranslationFieldExistRule(
                    FlowStateModel::class,
                    'name',
                    $locale,
                    $state->id,
                    -1,
                    ['flow_id' => $flowIdForRule],
                    'workflow::base.fields.name'
                );
                if (!$uniqueRule->passes("translation.$locale.name", $name)) {
                    throw ValidationException::withMessages([
                        "translation.$locale.name" => [$uniqueRule->message()],
                    ]);
                }

                $normalized[$locale] = [
                    'name' => $name,
                    'description' => $fields['description'] ?? null,
                ];
            }

            $data['translation'] = $normalized;
        }

        $defaults = [
            'is_terminal' => false,
            'color' => config('flow.state.default.color', '#fff'),
            'icon' => config('flow.state.default.icon', 'circle'),
            'position' => [
                'x' => 0,
                'y' => 0
            ],
        ];

        if (array_key_exists('is_terminal', $data)) {
            $config = is_array($data['config'] ?? null) ? $data['config'] : ($state->config ?? []);

            Arr::set($config, 'is_terminal', (bool)$data['is_terminal']);
            unset($data['is_terminal']);

            $data['config'] = array_replace_recursive($defaults, $config);
        } elseif (array_key_exists('config', $data) && is_array($data['config'])) {
            $config = array_replace_recursive($state->config ?? [], $data['config']);

            if (!array_key_exists('is_terminal', $config)) {
                $config['is_terminal'] = false;
            }

            $data['config'] = array_replace_recursive($defaults, $config);
        }

        return parent::update($id, $data, $with);
    }

    /**
     * Delete a state after enforcing invariants.
     *
     * @param int $id
     * @param array $with
     *
     * @return Response
     * @throws Throwable
     */
    public function doDestroy(int $id, array $with = []): Response
    {
        /** @var FlowStateModel $state */
        $state = FlowStateModel::query()->findOrFail($id);

        if ($state->is_start) {
            throw ValidationException::withMessages([
                'id' => [
                    trans('workflow::base.validation.flow_state.cannot_delete_start')
                ],
            ]);
        }

        return parent::destroy($id, $with);
    }

    /**
     * Runs right after model is persisted (create).
     *
     * @param Model $model
     * @param array $data
     *
     * @return void
     */
    protected function afterStore(Model $model, array &$data): void
    {
        $this->forgetCache();
    }

    /**
     * Runs right after model is persisted (update).
     *
     * @param Model $model
     * @param array $data
     *
     * @return void
     */
    protected function afterUpdate(Model $model, array &$data): void
    {
        $this->forgetCache();
    }

    /**
     * Runs right after deletion.
     *
     * @param Model $model
     *
     * @return void
     */
    protected function afterDestroy(Model $model): void
    {
        $this->forgetCache();
    }

    /**
     * Ensure each provided locale contains a non-empty "name".
     *
     * @param array $translations
     *
     * @return bool
     */
    private function translationsHaveName(array $translations): bool
    {
        foreach ($translations as $fields) {
            if (!is_array($fields) || trim((string)($fields['name'] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }
}
