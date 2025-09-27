<?php

namespace JobMetric\Flow\Facades;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use JobMetric\Flow\Models\Flow as FlowModel;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Flow\Services\Flow as FlowService;
use JobMetric\PackageCore\Output\Response;

/**
 * Flow Facade
 *
 * Provides a static interface to the Flow domain service which encapsulates
 * CRUD operations and domain logic for workflows (states, transitions, rollout, etc.).
 *
 * @mixin FlowService
 *
 * @method static Response store(array $data) Handle creation of a Flow entity using the underlying service.
 * @method static Response show(int $flowId, array $with = []) Retrieve a Flow entity (optionally with relations) wrapped in a Response.
 * @method static Response update(int $flowId, array $data, array $with = []) Update a Flow entity and return a Response with the updated resource.
 * @method static Response delete(int $flowId) Soft-delete a Flow entity and return an operation Response.
 * @method static Response restore(int $flowId) Restore a previously soft-deleted Flow entity and return a Response.
 * @method static Response forceDelete(int $flowId) Permanently delete a Flow entity and return a Response.
 *
 * @method static Response toggleStatus(int $flowId, array $with = []) Toggle the boolean 'status' field for a given Flow and return a Response with the resource.
 * @method static FlowState|null getStartState(int $flowId) Get the START state of a Flow or null if not found.
 * @method static FlowState|null getEndState(int $flowId) Get the END state of a Flow or null if not found.
 * @method static Response setDefault(int $flowId, array $with = []) Mark the given Flow as default within its scope and unset others.
 * @method static Response setActiveWindow(int $flowId, ?Carbon $from, ?Carbon $to, array $with = []) Set or clear active window dates for a Flow.
 * @method static Response setRollout(int $flowId, ?int $pct, array $with = []) Update rollout percentage (0..100) or reset when null.
 * @method static Response reorder(array $orderedIds) Reorder multiple flows by explicit id sequence.
 * @method static Response duplicate(int $flowId, array $overrides = [], bool $withGraph = true, array $with = []) Duplicate a flow, optionally with states and transitions, returning a Response with the copy.
 * @method static Collection<int, FlowState> getStates(int $flowId) Get all states for a Flow ordered by id.
 * @method static array<string, FlowState> getStatesByStatusMap(int $flowId) Get states keyed by their 'status' value for quick lookup.
 * @method static Response validateConsistency(int $flowId) Validate structural consistency (single START, no incoming to START, etc.) and return a Response with errors if any.
 * @method static FlowModel|null previewPick(Model $subject, ?callable $tuner = null) Preview which Flow would be picked for a given subject model.
 * @method static array export(int $flowId, bool $withGraph = true) Export a flow (optionally with graph) to an array payload.
 * @method static FlowModel import(array $payload, array $overrides = []) Import a flow payload and return the created Flow model.
 */
class Flow extends Facade
{
    /**
     * Get the registered name of the component in the service container.
     *
     * This accessor must match the binding defined in the package service provider.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'flow';
    }
}
