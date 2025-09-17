<?php

namespace JobMetric\Flow\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowTransition;

/**
 * Class FlowTaskResource
 *
 * Transforms the FlowTask model into a structured JSON resource.
 *
 * @property int $id
 * @property int $flow_transition_id
 * @property string $driver
 * @property array|null $config
 * @property int $ordering
 * @property bool $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read FlowTransition $transition
 * @property-read Flow|null $flow
 */
class FlowTaskResource extends JsonResource
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
            'flow_transition_id' => $this->flow_transition_id,
            'driver' => $this->driver,
            'config' => $this->config,
            'ordering' => $this->ordering,
            'status' => (bool)$this->status,

            // ISO 8601 timestamps for interoperability across clients
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Loaded relations
            'transition' => $this->whenLoaded('transition', function () {
                return FlowTransitionResource::make($this->transition);
            }),

            // if load relation 'transition.flow'
            'flow' => $this->when($this->relationLoaded('transition') && $this->transition->relationLoaded('flow'), function () {
                return FlowResource::make($this->transition->flow);
            }),
        ];
    }
}
