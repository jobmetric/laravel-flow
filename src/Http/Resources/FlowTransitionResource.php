<?php

namespace JobMetric\Flow\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowInstance;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Flow\Models\FlowTask;
use JobMetric\Translation\Http\Resources\TranslationCollectionResource;

/**
 * Class FlowTransitionResource
 *
 * Transforms the FlowTransition model into a structured JSON resource.
 *
 * @property int $id
 * @property int $flow_id
 * @property int|null $from
 * @property int|null $to
 * @property string|null $slug
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Flow $flow
 * @property-read FlowState|null $fromState
 * @property-read FlowState|null $toState
 * @property-read FlowTask[] $tasks
 * @property-read FlowInstance[] $instances
 * @property-read bool $is_start_edge
 * @property-read bool $is_end_edge
 */
class FlowTransitionResource extends JsonResource
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

            'translations' => $this->whenLoaded('translations', function () {
                return TranslationCollectionResource::make($this)
                    ->withLocale(app()->getLocale());
            }),

            'flow_id' => $this->flow_id,

            'from' => $this->from,
            'to' => $this->to,
            'slug' => $this->slug,

            'is_start_edge' => (bool)($this->is_start_edge ?? is_null($this->from) && !is_null($this->to)),
            'is_end_edge' => (bool)($this->is_end_edge ?? !is_null($this->from) && is_null($this->to)),

            // ISO 8601 timestamps for interoperability across clients
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Loaded relations
            'flow' => $this->whenLoaded('flow', function () {
                return FlowResource::make($this->flow);
            }),

            'from_state' => $this->whenLoaded('fromState', function () {
                return FlowStateResource::make($this->fromState);
            }),

            'to_state' => $this->whenLoaded('toState', function () {
                return FlowStateResource::make($this->toState);
            }),

            'tasks' => $this->whenLoaded('tasks', function () {
                return FlowTaskResource::collection($this->tasks);
            }),

            'instances' => $this->whenLoaded('instances', function () {
                return FlowInstanceResource::collection($this->instances);
            }),
        ];
    }
}
