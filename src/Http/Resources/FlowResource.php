<?php

namespace JobMetric\Flow\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use JobMetric\Flow\Models\FlowInstance;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Flow\Models\FlowTask;
use JobMetric\Flow\Models\FlowTransition;
use JobMetric\Flow\Models\FlowUse;

/**
 * Class FlowResource
 *
 * Transforms the Flow model into a structured JSON resource.
 *
 * @property int $id
 * @property string $subject_type
 * @property string|null $subject_scope
 * @property int $version
 * @property bool $is_default
 * @property bool $status
 * @property string|null $channel
 * @property int $ordering
 * @property int|null $rollout_pct
 * @property string|null $environment
 * @property Carbon|null $active_from
 * @property Carbon|null $active_to
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read FlowState[] $states
 * @property-read FlowTransition[] $transitions
 * @property-read FlowTask[] $tasks
 * @property-read FlowInstance[] $flowInstances
 * @property-read FlowUse[] $uses
 * @property-read FlowState|null $startState
 * @property-read FlowState|null $endState
 */
class FlowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject_type' => $this->subject_type,
            'subject_scope' => $this->subject_scope,

            'version' => $this->version,
            'is_default' => (bool)$this->is_default,
            'status' => (bool)$this->status,

            'channel' => $this->channel,
            'ordering' => $this->ordering,
            'rollout_pct' => $this->rollout_pct,
            'environment' => $this->environment,

            // ISO 8601 timestamps for interoperability across clients
            'active_from' => $this->active_from?->toISOString(),
            'active_to' => $this->active_to?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Loaded relations
            'states' => $this->whenLoaded('states', function () {
                return FlowStateResource::collection($this->states);
            }),

            'transitions' => $this->whenLoaded('transitions', function () {
                return FlowTransitionResource::collection($this->transitions);
            }),

            'tasks' => $this->whenLoaded('tasks', function () {
                return FlowTaskResource::collection($this->tasks);
            }),

            'flow_instances' => $this->whenLoaded('flowInstances', function () {
                return FlowInstanceResource::collection($this->flowInstances);
            }),

            'uses' => $this->whenLoaded('uses', function () {
                return FlowUseResource::collection($this->uses);
            }),

            'start_state' => $this->whenLoaded('startState', function () {
                return FlowStateResource::make($this->startState);
            }),

            'end_state' => $this->whenLoaded('endState', function () {
                return FlowStateResource::make($this->endState);
            }),
        ];
    }
}
