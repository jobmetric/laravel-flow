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
use JobMetric\Flow\Http\Requests\Flow\ReorderFlowRequest;
use JobMetric\Flow\Http\Requests\Flow\SetActiveWindowRequest;
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
 * CRUD and management service for Flow entities.
 * Responsibilities:
 * - Validate & normalize payloads via DTO helpers
 * - Handle START state creation on store
 * - Fire domain events and invalidate caches on mutations
 * - Provide helpers for defaulting, status toggling, active window, rollout, ordering, duplication, import/export
 */
class Flow extends AbstractCrudService
{
    /**
     * Enable soft-deletes + restore/forceDelete APIs.
     *
     * @var bool
     */
    protected bool $softDelete = true;

    /**
     * Human-readable entity name key used in response messages.
     *
     * @var string
     */
    protected string $entityName = 'workflow::base.entity_names.flow';

    /**
     * Bound model/resource classes for the base CRUD.
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

    /**
     * Domain events mapping for CRUD lifecycle.
     *
     * @var class-string|null
     */
    protected static ?string $storeEventClass = FlowStoreEvent::class;
    protected static ?string $updateEventClass = FlowUpdateEvent::class;
    protected static ?string $deleteEventClass = FlowDeleteEvent::class;
    protected static ?string $restoreEventClass = FlowRestoreEvent::class;
    protected static ?string $forceDeleteEventClass = FlowForceDeleteEvent::class;

    /**
     * Mutate/validate payload before create.
     *
     * Role: ensures a clean, validated input for store().
     *
     * @param array<string,mixed> $data
     *
     * @return void
     * @throws Throwable
     */
    protected function changeFieldStore(array &$data): void
    {
        $data = dto($data, StoreFlowRequest::class);
    }

    /**
     * Mutate/validate payload before update.
     *
     * Role: aligns input with update rules for the specific Flow.
     *
     * @param Model $model
     * @param array<string,mixed> $data
     *
     * @return void
     * @throws Throwable
     */
    protected function changeFieldUpdate(Model $model, array &$data): void
    {
        /** @var FlowModel $flow */
        $flow = $model;

        $data = dto($data, UpdateFlowRequest::class, [
            'flow_id' => $flow->id,
        ]);
    }

    /**
     * Common hook executed after all mutation operations.
     * Invalidates flow-related caches.
     *
     * @param string $operation The operation being performed:
     *                          'store'|'update'|'destroy'|'restore'|'forceDelete'|'toggleStatus'|'setDefault'|'setActiveWindow'|'setRollout'|'reorder'|'duplicate'|'import'
     * @param Model $model      The model instance
     * @param array $data       The data payload (empty for destroy/restore/forceDelete)
     *
     * @return void
     */
    protected function afterCommon(string $operation, Model $model, array $data = []): void
    {
        forgetFlowCache();
    }

    /**
     * Hook after create: ensure one START state and invalidate caches.
     *
     * @param Model $model
     * @param array<string,mixed> $data
     *
     * @return void
     */
    protected function afterStore(Model $model, array &$data): void
    {
        /** @var FlowModel $flow */
        $flow = $model;

        // Build default START translations for active locales.
        $locales = Language::getActiveLocales();

        $translations = [];
        foreach ($locales as $locale) {
            $translations[$locale] = [
                'name'        => trans('workflow::base.states.start.name', [], $locale),
                'description' => trans('workflow::base.states.start.description', [], $locale),
            ];
        }

        // Ensure one START node exists.
        $flow->states()->create([
            'translation' => $translations,
            'type'        => FlowStateTypeEnum::START(),
            'config'      => [
                'is_terminal' => false,
                'color'       => config('flow.state.start.color', '#fff'),
                'icon'        => config('flow.state.start.icon', 'play'),
                'position'    => [
                    'x' => config('flow.state.start.position.x', 0),
                    'y' => config('flow.state.start.position.y', 0),
                ],
            ],
        ]);
    }

    /**
     * Toggle the boolean 'status' field for a given Flow.
     *
     * Role: quick enable/disable switch.
     *
     * @param int $flowId
     * @param array<int,string> $with
     *
     * @return Response
     * @throws Throwable
     */
    public function toggleStatus(int $flowId, array $with = []): Response
    {
        return DB::transaction(function () use ($flowId, $with) {
            /** @var FlowModel $flow */
            $flow = FlowModel::query()->findOrFail($flowId);

            $flow->status = ! $flow->status;
            $flow->save();

            $this->afterCommon('toggleStatus', $flow);

            return Response::make(true, trans('workflow::base.messages.toggle_status', [
                'entity' => trans($this->entityName),
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
        return FlowState::start()->where('flow_id', $flowId)->first();
    }

    /**
     * Get the END states of a Flow.
     *
     * @param int $flowId
     *
     * @return Collection
     */
    public function getEndState(int $flowId): Collection
    {
        return FlowState::end()->where('flow_id', $flowId)->get();
    }

    /**
     * Mark a flow as default within its (subject_type + subject_scope + version) scope.
     * Unsets other defaults within the same scope.
     *
     * @param int $flowId
     * @param array<int,string> $with
     *
     * @return Response
     * @throws Throwable
     */
    public function setDefault(int $flowId, array $with = []): Response
    {
        return DB::transaction(function () use ($flowId, $with) {
            /** @var FlowModel $flow */
            $flow = FlowModel::query()->lockForUpdate()->findOrFail($flowId);

            $scopeQuery = FlowModel::query()
                ->where('subject_type', $flow->subject_type)
                ->where('version', $flow->version)
                ->when(is_null($flow->subject_scope), fn ($q) => $q->whereNull('subject_scope'), fn ($q
                ) => $q->where('subject_scope', $flow->subject_scope));

            $scopeQuery->whereKeyNot($flow->getKey())->update(['is_default' => false]);
            FlowModel::query()->whereKey($flow->getKey())->update(['is_default' => true]);

            $flow->refresh();
            $this->afterCommon('setDefault', $flow);

            return Response::make(true, trans('workflow::base.messages.set_default', [
                'entity' => trans($this->entityName),
            ]), FlowResource::make($flow->load($with)));
        });
    }

    /**
     * Set active window dates (nullable to clear).
     *
     * @param int $flowId
     * @param Carbon|null $from
     * @param Carbon|null $to
     * @param array<int,string> $with
     *
     * @return Response
     * @throws Throwable
     */
    public function setActiveWindow(int $flowId, ?Carbon $from, ?Carbon $to, array $with = []): Response
    {
        $validated = dto([
            'active_from' => $from?->toDateTimeString(),
            'active_to'   => $to?->toDateTimeString(),
        ], SetActiveWindowRequest::class);

        $from = isset($validated['active_from']) ? Carbon::parse($validated['active_from']) : null;
        $to = isset($validated['active_to']) ? Carbon::parse($validated['active_to']) : null;

        return DB::transaction(function () use ($flowId, $from, $to, $with) {
            /** @var FlowModel $flow */
            $flow = FlowModel::query()->findOrFail($flowId);

            $flow->active_from = $from;
            $flow->active_to = $to;
            $flow->save();

            $this->afterCommon('setActiveWindow', $flow);

            return Response::make(true, trans('workflow::base.messages.set_active_window', [
                'entity' => trans($this->entityName),
            ]), FlowResource::make($flow->load($with)));
        });
    }

    /**
     * Update rollout percentage (0..100). Null resets to 100% (no canary).
     *
     * @param int $flowId
     * @param int|null $pct
     * @param array<int,string> $with
     *
     * @return Response
     * @throws Throwable
     */
    public function setRollout(int $flowId, ?int $pct, array $with = []): Response
    {
        $validated = dto([
            'rollout_pct' => $pct,
        ], SetRolloutRequest::class);

        $pct = $validated['rollout_pct'] ?? null;

        return DB::transaction(function () use ($flowId, $pct, $with) {
            /** @var FlowModel $flow */
            $flow = FlowModel::query()->findOrFail($flowId);
            $flow->rollout_pct = $pct;
            $flow->save();

            $this->afterCommon('setRollout', $flow);

            return Response::make(true, trans('workflow::base.messages.set_rollout', [
                'entity' => trans($this->entityName),
            ]), FlowResource::make($flow->load($with)));
        });
    }

    /**
     * Reorder multiple flows by explicit id sequence.
     *
     * @param array<int,int> $orderedIds
     *
     * @return Response
     * @throws Throwable
     */
    public function reorder(array $orderedIds): Response
    {
        $validated = dto([
            'ordered_ids' => $orderedIds,
        ], ReorderFlowRequest::class);

        $orderedIds = $validated['ordered_ids'];

        return DB::transaction(function () use ($orderedIds) {
            $position = 1;
            $firstModel = null;
            foreach ($orderedIds as $id) {
                FlowModel::query()->whereKey($id)->update([
                    'ordering' => $position++,
                ]);
                if ($firstModel === null) {
                    $firstModel = FlowModel::query()->find($id);
                }
            }

            if ($firstModel) {
                $this->afterCommon('reorder', $firstModel);
            }

            return Response::make(true, trans('workflow::base.messages.reordered', [
                'entity' => trans($this->entityName),
            ]));
        });
    }

    /**
     * Duplicate a flow (optionally with states & transitions).
     * Transitions table columns are: id, flow_id, from, to, slug, timestamps.
     *
     * @param int $flowId
     * @param array<string,mixed> $overrides
     * @param bool $withGraph
     * @param array<int,string> $with
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
                'id'         => null,
                'is_default' => false,
                'status'     => false,
                'version'    => ($flow->version ?? 1) + 1,
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
                        'translation' => $state->getTranslations(),
                        'type'        => $state->type,
                        'config'      => $state->config,
                        'status'      => $state->status,
                    ]);

                    $mapStateId[$state->id] = $newState->id;
                }

                if (method_exists($flow, 'transitions')) {
                    foreach ($flow->transitions as $transition) {
                        $copy->transitions()->create([
                            'from' => $transition->from ? ($mapStateId[$transition->from] ?? null) : null,
                            'to'   => $transition->to ? ($mapStateId[$transition->to] ?? null) : null,
                            'slug' => $transition->slug,
                        ]);
                    }
                }
            }

            $this->afterCommon('duplicate', $copy);

            return Response::make(true, trans('workflow::base.messages.duplicated', [
                'entity' => trans($this->entityName),
            ]), FlowResource::make($copy->load($with)));
        });
    }

    /**
     * Get all states for a flow ordered by id.
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
     * Get states keyed by "status" for quick lookup.
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
     * @param int $flowId
     *
     * @return Response
     * @throws Throwable
     */
    public function validateConsistency(int $flowId): Response
    {
        $errors = [];

        $states = FlowState::query()->where('flow_id', $flowId)->get();

        // Exactly one START
        $startStates = $states->where('type', FlowStateTypeEnum::START);
        if ($startStates->count() !== 1) {
            $errors['flow'][] = trans('workflow::base.validation.start_required');
        }

        // START must not have incoming transitions
        if ($startStates->count() === 1) {
            $startId = $startStates->first()->id;

            // Prefer relation if it exists
            $flow = FlowModel::query()->find($flowId);

            if ($flow && method_exists($flow, 'transitions')) {
                $incomingCount = (int) $flow->transitions()->where('to', $startId)->count();
            }
            else {
                $incomingCount = (int) FlowTransition::query()
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
     * @param callable(FlowPickerBuilder):void|null $tuner Optional tuner to adjust builder before pick.
     *
     * @return FlowModel|null
     */
    public function previewPick(Model $subject, ?callable $tuner = null): ?FlowModel
    {
        $builder = new FlowPickerBuilder();

        if (method_exists($subject, 'buildFlowPicker')) {
            $subject->buildFlowPicker($builder);
        }
        else {
            $builder->subjectType(get_class($subject))
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
     * @return array<string,mixed>
     */
    public function export(int $flowId, bool $withGraph = true): array
    {
        /** @var FlowModel $flow */
        $flow = FlowModel::query()
            ->when($withGraph, fn ($q) => $q->with(['states', 'transitions']))
            ->findOrFail($flowId);

        return [
            'flow'        => collect($flow->toArray())->only([
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
            'states'      => $withGraph ? $flow->states->toArray() : [],
            'transitions' => $withGraph && method_exists($flow, 'transitions') ? $flow->transitions->toArray() : [],
        ];
    }

    /**
     * Import a flow payload; returns created flow.
     * Transitions array must contain keys: from, to, slug.
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $overrides
     *
     * @return FlowModel
     * @throws Throwable
     */
    public function import(array $payload, array $overrides = []): FlowModel
    {
        return DB::transaction(function () use ($payload, $overrides) {
            $flowData = array_merge($payload['flow'] ?? [], $overrides, [
                'is_default' => $overrides['is_default'] ?? false,
                'status'     => $overrides['status'] ?? false,
            ]);

            /** @var FlowModel $flow */
            $flow = FlowModel::query()->create($flowData);

            // Map old state ids to new ones for transition remap.
            $stateIdMap = [];
            foreach ($payload['states'] ?? [] as $state) {
                $oldId = $state['id'] ?? null;

                unset($state['id'], $state['flow_id'], $state['created_at'], $state['updated_at']);

                $new = $flow->states()->create($state);

                if ($oldId) {
                    $stateIdMap[$oldId] = $new->id;
                }
            }

            if (! empty($payload['transitions']) && method_exists($flow, 'transitions')) {
                foreach ($payload['transitions'] as $transition) {
                    unset($transition['id'], $transition['flow_id'], $transition['created_at'], $transition['updated_at']);

                    if (array_key_exists('from', $transition)) {
                        $transition['from'] = $transition['from'] ? ($stateIdMap[$transition['from']] ?? null) : null;
                    }

                    if (array_key_exists('to', $transition)) {
                        $transition['to'] = $transition['to'] ? ($stateIdMap[$transition['to']] ?? null) : null;
                    }

                    $payloadTransition = collect($transition)->only(['from', 'to', 'slug'])->all();

                    $flow->transitions()->create($payloadTransition);
                }
            }

            $this->afterCommon('import', $flow);

            return $flow;
        });
    }
}
