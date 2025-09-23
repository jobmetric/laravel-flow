<?php

namespace JobMetric\Flow\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Flow\Events\Flow\FlowDeleteEvent;
use JobMetric\Flow\Events\Flow\FlowForceDeleteEvent;
use JobMetric\Flow\Events\Flow\FlowRestoreEvent;
use JobMetric\Flow\Events\Flow\FlowStoreEvent;
use JobMetric\Flow\Events\Flow\FlowUpdateEvent;
use JobMetric\Flow\Http\Resources\FlowResource;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Flow\Support\FlowPicker;
use JobMetric\Flow\Support\FlowPickerBuilder;
use JobMetric\Language\Facades\Language;
use JobMetric\PackageCore\Output\Response;
use JobMetric\PackageCore\Services\AbstractCrudService;
use Throwable;

/**
 * Class FlowManager
 *
 * Flow CRUD service built on top of AbstractCrudService.
 * - Uses Laravel API Resources (FlowResource) for output
 * - Handles START state creation on store
 * - Fires domain events on store/update/delete/restore/forceDelete
 * - Clears related caches after mutations
 * - Adds a toggleStatus() helper
 * - Provides driver resolution helpers
 */
class FlowManager extends AbstractCrudService
{
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
    protected static string $modelClass = Flow::class;
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
     * Runs right after model is persisted (create).
     *
     * @param Model $model
     * @param array $data
     *
     * @return void
     */
    protected function afterStore(Model $model, array &$data): void
    {
        /** @var Flow $flow */
        $flow = $model;

        // Get active languages
        $languages = Language::all([
            'status' => true
        ]);

        $translations = [];
        foreach ($languages as $language) {
            $translations[$language->locale] = [
                'name' => trans('workflow::base.states.start.name', [], $language->locale),
                'description' => trans('workflow::base.states.start.description', [], $language->locale)
            ];
        }

        // Ensure one START node exists.
        $flow->states()->create([
            'translation' => $translations,
            'type' => FlowStateTypeEnum::START(),
            'config' => [
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
            /** @var Flow $flow */
            $flow = Flow::query()->findOrFail($flowId);

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
            /** @var Flow $flow */
            $flow = Flow::query()->findOrFail($flowId);

            $q = Flow::query()->where('subject_type', $flow->subject_type)
                ->where('version', $flow->version);

            if (is_null($flow->subject_scope)) {
                $q->whereNull('subject_scope');
            } else {
                $q->where('subject_scope', $flow->subject_scope);
            }

            $q->update(['is_default' => false]);

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
            /** @var Flow $flow */
            $flow = Flow::query()->findOrFail($flowId);

            if ($from && $to && $from->greaterThan($to)) {
                return Response::make(false, trans('workflow::base.messages.invalid_active_window'));
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
        return DB::transaction(function () use ($flowId, $pct, $with) {
            if (!is_null($pct) && ($pct < 0 || $pct > 100)) {
                return Response::make(false, trans('workflow::base.messages.invalid_rollout'));
            }

            /** @var Flow $flow */
            $flow = Flow::query()->findOrFail($flowId);
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
        return DB::transaction(function () use ($orderedIds) {
            $position = 1;
            foreach ($orderedIds as $id) {
                Flow::query()->whereKey($id)->update(['ordering' => $position++]);
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
            /** @var Flow $flow */
            $flow = Flow::query()->with(['states', 'transitions'])->findOrFail($flowId);

            $data = array_merge($flow->toArray(), $overrides, [
                'id' => null,
                'is_default' => false,
                'status' => false,
                'version' => ($flow->version ?? 1) + 1,
            ]);

            /** @var Flow $copy */
            $copy = Flow::query()->create(collect($data)->only([
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
                    foreach ($flow->transitions as $t) {
                        $copy->transitions()->create([
                            'from' => $t->from ? ($mapStateId[$t->from] ?? null) : null,
                            'to' => $t->to ? ($mapStateId[$t->to] ?? null) : null,
                            'slug' => $t->slug,
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
     * Validate graph consistency of a flow definition.
     * Ensures exactly one START, at most one END, and no duplicate status across states.
     *
     * @param int $flowId
     *
     * @return Response
     */
    public function validateConsistency(int $flowId): Response
    {
        $errors = [];

        $states = FlowState::query()->where('flow_id', $flowId)->get();
        $start = $states->where('type', FlowStateTypeEnum::START())->count();
        $end = $states->where('type', FlowStateTypeEnum::END())->count();

        if ($start !== 1) {
            $errors[] = 'Flow must have exactly one START state.';
        }

        if ($end > 1) {
            $errors[] = 'Flow must have at most one END state.';
        }

        $nonNullStatuses = $states->whereNotNull('status')->pluck('status');
        if ($nonNullStatuses->count() !== $nonNullStatuses->unique()->count()) {
            $errors[] = 'Duplicate status values detected across states.';
        }

        return $errors
            ? Response::make(false, trans('workflow::base.messages.flow_invalid'), ['errors' => $errors])
            : Response::make(true, trans('workflow::base.messages.flow_valid'));
    }

    /**
     * Preview which flow would be picked for a given subject model.
     *
     * @param Model $subject
     * @param callable(FlowPickerBuilder):void|null $tuner
     *
     * @return Flow|null
     */
    public function previewPick(Model $subject, ?callable $tuner = null): ?Flow
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
        /** @var Flow $flow */
        $flow = Flow::query()
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
     * @return Flow
     * @throws Throwable
     */
    public function import(array $payload, array $overrides = []): Flow
    {
        return DB::transaction(function () use ($payload, $overrides) {
            $flowData = array_merge($payload['flow'] ?? [], $overrides, [
                'is_default' => $overrides['is_default'] ?? false,
                'status' => $overrides['status'] ?? false,
            ]);

            /** @var Flow $flow */
            $flow = Flow::query()->create($flowData);

            $stateIdMap = [];
            foreach ($payload['states'] ?? [] as $s) {
                $oldId = $s['id'] ?? null;

                unset($s['id'], $s['flow_id'], $s['created_at'], $s['updated_at']);

                $new = $flow->states()->create($s);

                if ($oldId) {
                    $stateIdMap[$oldId] = $new->id;
                }
            }

            if (!empty($payload['transitions']) && method_exists($flow, 'transitions')) {
                foreach ($payload['transitions'] as $t) {
                    unset($t['id'], $t['flow_id'], $t['created_at'], $t['updated_at']);

                    if (array_key_exists('from', $t)) {
                        $t['from'] = $t['from'] ? ($stateIdMap[$t['from']] ?? null) : null;
                    }

                    if (array_key_exists('to', $t)) {
                        $t['to'] = $t['to'] ? ($stateIdMap[$t['to']] ?? null) : null;
                    }

                    // keep only columns that exist on flow_transition
                    $payloadTransition = collect($t)->only(['from', 'to', 'slug'])->all();

                    $flow->transitions()->create($payloadTransition);
                }
            }

            $this->forgetCache();

            return $flow;
        });
    }

    /**
     * Forget caches related to flow picking/registry (called after mutations).
     *
     * @return void
     */
    protected function forgetCache(): void
    {
        cache()->forget('flows');
        cache()->forget('flow.pick');
    }
}
