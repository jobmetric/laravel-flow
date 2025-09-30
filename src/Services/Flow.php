<?php

namespace JobMetric\Flow\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Flow\Events\Flow\FlowDeleteEvent;
use JobMetric\Flow\Events\Flow\FlowForceDeleteEvent;
use JobMetric\Flow\Events\Flow\FlowRestoreEvent;
use JobMetric\Flow\Events\Flow\FlowStoreEvent;
use JobMetric\Flow\Events\Flow\FlowUpdateEvent;
use JobMetric\Flow\Exceptions\InvalidActiveWindowException;
use JobMetric\Flow\Http\Requests\Flow\ReorderFlowRequest;
use JobMetric\Flow\Http\Requests\Flow\SetRolloutRequest;
use JobMetric\Flow\Http\Requests\Flow\StoreFlowRequest;
use JobMetric\Flow\Http\Requests\Flow\UpdateFlowRequest;
use JobMetric\Flow\Http\Resources\FlowResource;
use JobMetric\Flow\Models\Flow as FlowModel;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Flow\Models\FlowTransition;
use JobMetric\Flow\Support\FlowPicker;
use JobMetric\Flow\Support\FlowPickerBuilder;
use JobMetric\Language\Facades\Language;
use JobMetric\PackageCore\Output\Response;
use JobMetric\PackageCore\Services\AbstractCrudService;
use Throwable;

/**
 * Class Flow
 *
 * Flow CRUD service built on top of AbstractCrudService.
 * - Uses Laravel API Resources (FlowResource) for output
 * - Handles START state creation on store
 * - Fires domain events on store/update/delete/restore/forceDelete
 * - Clears related caches after mutations
 * - Adds a toggleStatus() helper
 * - Provides driver resolution helpers
 */
class Flow extends AbstractCrudService
{
    use InvalidatesFlowCache;

    /**
     * Enable soft-deletes + restore/forceDelete APIs.
     *
     * @var bool
     */
    protected bool $softDelete = true;

    /**
     * Human-readable entity name used in response messages.
     *
     * @var string
     */
    protected string $entityName = 'workflow::base.entity_names.flow';

    /**
     * Bind model/resource classes for the base CRUD.
     *
     * @var class-string
     */
    protected static string $modelClass = FlowModel::class;
    protected static string $resourceClass = FlowResource::class;

    /**
     * Allowed fields for selection/filter/sort in QueryBuilder.
     *
     * @var string[]
     */
    protected static array $fields = [
        'id',
        'subject_type',
        'subject_scope',
        'subject_collection',
        'version',
        'is_default',
        'status',
        'active_from',
        'active_to',
        'channel',
        'ordering',
        'rollout_pct',
        'environment',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected static ?string $storeEventClass = FlowStoreEvent::class;
    protected static ?string $updateEventClass = FlowUpdateEvent::class;
    protected static ?string $deleteEventClass = FlowDeleteEvent::class;
    protected static ?string $restoreEventClass = FlowRestoreEvent::class;
    protected static ?string $forceDeleteEventClass = FlowForceDeleteEvent::class;

    /**
     * Mutate/normalize payload before create.
     *
     * @param array $data
     *
     * @return void
     * @throws Throwable
     */
    protected function changeFieldStore(array &$data): void
    {
        $data = dto($data, StoreFlowRequest::class);
    }

    /**
     * Mutate/normalize payload before update.
     *
     * @param Model $model
     * @param array $data
     *
     * @return void
     * @throws Throwable
     */
    protected function changeFieldUpdate(Model $model, array &$data): void
    {
        /** @var FlowModel $flow */
        $flow = $model;

        $data = dto($data, UpdateFlowRequest::class, [
            'flow_id' => $flow->id
        ]);
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
        /** @var FlowModel $flow */
        $flow = $model;

        // Get active languages
        $locales = Language::all([
            'status' => true
        ])->pluck('locale')->all();

        $translations = [];
        foreach ($locales as $locale) {
            $translations[$locale] = [
                'name' => trans('workflow::base.states.start.name', [], $locale),
                'description' => trans('workflow::base.states.start.description', [], $locale)
            ];
        }

        // Ensure one START node exists.
        $flow->states()->create([
            'translation' => $translations,
            'type' => FlowStateTypeEnum::START(),
            'config' => [
                'is_terminal' => false,
                'color' => config('flow.state.start.color', '#fff'),
                'icon' => config('flow.state.start.icon', 'play'),
                'position' => [
                    'x' => config('flow.state.start.position.x', 0),
                    'y' => config('flow.state.start.position.y', 0),
                ],
            ],
        ]);

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
     * Runs right after restore.
     *
     * @param Model $model
     *
     * @return void
     */
    protected function afterRestore(Model $model): void
    {
        $this->forgetCache();
    }

    /**
     * Runs just before force delete.
     *
     * @param Model $model
     *
     * @return void
     */
    protected function afterForceDelete(Model $model): void
    {
        $this->forgetCache();
    }

    /**
     * Toggle the boolean 'status' field for a given Flow.
     *
     * @param int $flowId
     * @param array $with
     *
     * @return Response
     * @throws Throwable
     */
    public function toggleStatus(int $flowId, array $with = []): Response
    {
        return DB::transaction(function () use ($flowId, $with) {
            /** @var FlowModel $flow */
            $flow = FlowModel::query()->findOrFail($flowId);

            $flow->status = !$flow->status;
            $flow->save();

            $this->forgetCache();

            return Response::make(true, trans('workflow::base.messages.toggle_status', [
                'entity' => trans('workflow::base.entity_names.flow'),
            ]), FlowResource::make($flow->load($with)));
        });
    }

    /**
     * Get the START state of a Flow.
     *
     * @param int $flowId
     *
     * @return FlowState|null
     */
    public function getStartState(int $flowId): ?FlowState
    {
        return FlowState::query()->where([
            'flow_id' => $flowId,
            'type' => FlowStateTypeEnum::START(),
        ])->first();
    }

    /**
     * Get the END state of a Flow.
     *
     * @param int $flowId
     *
     * @return FlowState|null
     */
    public function getEndState(int $flowId): ?FlowState
    {
        return FlowState::query()->where([
            'flow_id' => $flowId,
            'type' => FlowStateTypeEnum::END(),
        ])->first();
    }

    /**
     * Mark a flow as default within (subject_type + subject_scope + version) scope.
     * Unsets other defaults within the same scope.
     *
     * @param int $flowId
     * @param array $with
     *
     * @return Response
     * @throws Throwable
     */
    public function setDefault(int $flowId, array $with = []): Response
    {
        return DB::transaction(function () use ($flowId, $with) {
            /** @var FlowModel $flow */
            $flow = FlowModel::query()->findOrFail($flowId);

            $query = FlowModel::query()->where('subject_type', $flow->subject_type)
                ->where('version', $flow->version);

            if (is_null($flow->subject_scope)) {
                $query->whereNull('subject_scope');
            } else {
                $query->where('subject_scope', $flow->subject_scope);
            }

            $query->update(['is_default' => false]);

            $flow->is_default = true;
            $flow->save();

            $this->forgetCache();

            return Response::make(true, trans('workflow::base.messages.set_default', [
                'entity' => trans('workflow::base.entity_names.flow'),
            ]), FlowResource::make($flow->load($with)));
        });
    }

    /**
     * Set active window dates (nullable to clear).
     *
     * @param int $flowId
     * @param Carbon|null $from
     * @param Carbon|null $to
     * @param array $with
     *
     * @return Response
     * @throws Throwable
     */
    public function setActiveWindow(int $flowId, ?Carbon $from, ?Carbon $to, array $with = []): Response
    {
        return DB::transaction(function () use ($flowId, $from, $to, $with) {
            /** @var FlowModel $flow */
            $flow = FlowModel::query()->findOrFail($flowId);

            if ($from && $to && $from->greaterThan($to)) {
                throw new InvalidActiveWindowException;
            }

            $flow->active_from = $from;
            $flow->active_to = $to;
            $flow->save();

            $this->forgetCache();

            return Response::make(true, trans('workflow::base.messages.set_active_window', [
                'entity' => trans('workflow::base.entity_names.flow'),
            ]), FlowResource::make($flow->load($with)));
        });
    }

    /**
     * Update rollout percentage (0..100). Null resets to 100% (no canary).
     *
     * @param int $flowId
     * @param int|null $pct
     * @param array $with
     *
     * @return Response
     * @throws Throwable
     */
    public function setRollout(int $flowId, ?int $pct, array $with = []): Response
    {
        $validated = dto([
            'rollout_pct' => $pct
        ], SetRolloutRequest::class);

        $pct = $validated['rollout_pct'] ?? null;

        return DB::transaction(callback: function () use ($flowId, $pct, $with) {
            /** @var FlowModel $flow */
            $flow = FlowModel::query()->findOrFail($flowId);
            $flow->rollout_pct = $pct;
            $flow->save();

            $this->forgetCache();

            return Response::make(true, trans('workflow::base.messages.set_rollout', [
                'entity' => trans('workflow::base.entity_names.flow'),
            ]), FlowResource::make($flow->load($with)));
        });
    }

    /**
     * Reorder multiple flows by explicit id sequence.
     *
     * @param array<int,int> $orderedIds List of flow ids in the desired order.
     *
     * @return Response
     * @throws Throwable
     */
    public function reorder(array $orderedIds): Response
    {
        $validated = dto([
            'ordered_ids' => $orderedIds
        ], ReorderFlowRequest::class);

        $orderedIds = $validated['ordered_ids'];

        return DB::transaction(function () use ($orderedIds) {
            $position = 1;
            foreach ($orderedIds as $id) {
                FlowModel::query()->whereKey($id)->update(['ordering' => $position++]);
            }

            $this->forgetCache();

            return Response::make(true, trans('workflow::base.messages.reordered', [
                'entity' => trans('workflow::base.entity_names.flow'),
            ]));
        });
    }

    /**
     * Duplicate a flow (optionally with states & transitions).
     * Transitions table columns are: id, flow_id, from, to, slug, timestamps.
     *
     * @param int $flowId
     * @param array $overrides
     * @param bool $withGraph
     * @param array $with
     *
     * @return Response
     * @throws Throwable
     */
    public function duplicate(int $flowId, array $overrides = [], bool $withGraph = true, array $with = []): Response
    {
        return DB::transaction(function () use ($flowId, $overrides, $withGraph, $with) {
            /** @var FlowModel $flow */
            $flow = FlowModel::query()->with(['states', 'transitions'])->findOrFail($flowId);

            $data = array_merge($flow->toArray(), $overrides, [
                'id' => null,
                'is_default' => false,
                'status' => false,
                'version' => ($flow->version ?? 1) + 1,
            ]);

            /** @var FlowModel $copy */
            $copy = FlowModel::query()->create(collect($data)->only([
                'subject_type',
                'subject_scope',
                'subject_collection',
                'version',
                'is_default',
                'status',
                'active_from',
                'active_to',
                'channel',
                'ordering',
                'rollout_pct',
                'environment',
            ])->all());

            if ($withGraph) {
                $mapStateId = [];

                foreach ($flow->states as $state) {
                    /** @var FlowState $newState */
                    $newState = $copy->states()->create([
                        'translation' => $state->translation,
                        'type' => $state->type,
                        'config' => $state->config,
                        'status' => $state->status,
                    ]);

                    $mapStateId[$state->id] = $newState->id;
                }

                // Copy transitions (columns: from, to, slug)
                if (method_exists($flow, 'transitions')) {
                    foreach ($flow->transitions as $transition) {
                        $copy->transitions()->create([
                            'from' => $transition->from ? ($mapStateId[$transition->from] ?? null) : null,
                            'to' => $transition->to ? ($mapStateId[$transition->to] ?? null) : null,
                            'slug' => $transition->slug,
                        ]);
                    }
                }
            }

            $this->forgetCache();

            return Response::make(true, trans('workflow::base.messages.duplicated', [
                'entity' => trans('workflow::base.entity_names.flow'),
            ]), FlowResource::make($copy->load($with)));
        });
    }

    /**
     * Get all states for a flow.
     *
     * @param int $flowId
     *
     * @return Collection<int,FlowState>
     */
    public function getStates(int $flowId): Collection
    {
        return FlowState::query()->where('flow_id', $flowId)->orderBy('id')->get();
    }

    /**
     * Get states keyed by status for quick lookup.
     *
     * @param int $flowId
     *
     * @return array<string,FlowState>
     */
    public function getStatesByStatusMap(int $flowId): array
    {
        return $this->getStates($flowId)->keyBy('status')->all();
    }

    /**
     * Validate structural consistency of a flow definition.
     *
     * Rules:
     * - Exactly one START state must exist.
     * - START state must not have any incoming transitions.
     * - State "status" values may repeat (no uniqueness check).
     *
     * @param int $flowId The target flow identifier to validate.
     *
     * @return Response
     * @throws Throwable
     */
    public function validateConsistency(int $flowId): Response
    {
        $errors = [];

        // Load states for the flow
        $states = FlowState::query()->where('flow_id', $flowId)->get();

        // Exactly one START
        $startStates = $states->where('type', FlowStateTypeEnum::START());
        if ($startStates->count() !== 1) {
            $errors['flow'][] = trans('workflow::base.validation.start_required');
        }

        // START must not have incoming transitions
        if ($startStates->count() === 1) {
            $startId = $startStates->first()->id;

            // Prefer relation if it exists
            $flow = FlowModel::query()->find($flowId);

            if ($flow && method_exists($flow, 'transitions')) {
                $incomingCount = (int)$flow->transitions()->where('to', $startId)->count();
            } else {
                $incomingCount = (int)FlowTransition::query()
                    ->where('flow_id', $flowId)
                    ->where('to', $startId)
                    ->count();
            }

            if ($incomingCount > 0) {
                $errors['flow'][] = trans('workflow::base.validation.start_must_not_have_incoming');
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return Response::make(true, trans('workflow::base.messages.flow_valid'));
    }

    /**
     * Preview which flow would be picked for a given subject model.
     *
     * @param Model $subject
     * @param callable(FlowPickerBuilder):void|null $tuner
     *
     * @return FlowModel|null
     */
    public function previewPick(Model $subject, ?callable $tuner = null): ?FlowModel
    {
        $builder = new FlowPickerBuilder();

        if (method_exists($subject, 'buildFlowPicker')) {
            $subject->buildFlowPicker($builder);
        } else {
            $builder
                ->subjectType(get_class($subject))
                ->subjectCollection(method_exists($subject, 'flowSubjectCollection') ? $subject->flowSubjectCollection() : null)
                ->onlyActive(true)
                ->timeNow(Carbon::now('UTC'))
                ->orderByDefault()
                ->evaluateRollout(true);
        }

        if ($tuner) {
            $tuner($builder);
        }

        return (new FlowPicker)->pick($subject, $builder);
    }

    /**
     * Export a flow (optionally with graph) to array/json-serializable structure.
     *
     * @param int $flowId
     * @param bool $withGraph
     *
     * @return array
     */
    public function export(int $flowId, bool $withGraph = true): array
    {
        /** @var FlowModel $flow */
        $flow = FlowModel::query()
            ->when($withGraph, fn($q) => $q->with(['states', 'transitions']))
            ->findOrFail($flowId);

        return [
            'flow' => collect($flow->toArray())->only([
                'subject_type',
                'subject_scope',
                'subject_collection',
                'version',
                'is_default',
                'status',
                'active_from',
                'active_to',
                'channel',
                'ordering',
                'rollout_pct',
                'environment',
            ])->all(),
            'states' => $withGraph ? $flow->states->toArray() : [],
            'transitions' => $withGraph && method_exists($flow, 'transitions') ? $flow->transitions->toArray() : [],
        ];
    }

    /**
     * Import a flow payload; returns created flow.
     * Transitions array must contain keys: from, to, slug.
     *
     * @param array $payload
     * @param array $overrides
     *
     * @return FlowModel
     * @throws Throwable
     */
    public function import(array $payload, array $overrides = []): FlowModel
    {
        return DB::transaction(function () use ($payload, $overrides) {
            $flowData = array_merge($payload['flow'] ?? [], $overrides, [
                'is_default' => $overrides['is_default'] ?? false,
                'status' => $overrides['status'] ?? false,
            ]);

            /** @var FlowModel $flow */
            $flow = FlowModel::query()->create($flowData);

            $stateIdMap = [];
            foreach ($payload['states'] ?? [] as $state) {
                $oldId = $state['id'] ?? null;

                unset($state['id'], $state['flow_id'], $state['created_at'], $state['updated_at']);

                $new = $flow->states()->create($state);

                if ($oldId) {
                    $stateIdMap[$oldId] = $new->id;
                }
            }

            if (!empty($payload['transitions']) && method_exists($flow, 'transitions')) {
                foreach ($payload['transitions'] as $transition) {
                    unset($transition['id'], $transition['flow_id'], $transition['created_at'], $transition['updated_at']);

                    if (array_key_exists('from', $transition)) {
                        $transition['from'] = $transition['from'] ? ($stateIdMap[$transition['from']] ?? null) : null;
                    }

                    if (array_key_exists('to', $transition)) {
                        $transition['to'] = $transition['to'] ? ($stateIdMap[$transition['to']] ?? null) : null;
                    }

                    // keep only columns that exist on flow_transition
                    $payloadTransition = collect($transition)->only(['from', 'to', 'slug'])->all();

                    $flow->transitions()->create($payloadTransition);
                }
            }

            $this->forgetCache();

            return $flow;
        });
    }
}
