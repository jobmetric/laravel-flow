<?php

namespace JobMetric\Flow\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Flow\Models\FlowTask;
use JobMetric\Flow\Models\FlowTransition;

/**
 * Class FlowStateResource
 *
 * Transforms the FlowState model into a structured JSON resource.
 *
 * @property int $id
 * @property int $flow_id
 * @property mixed $type
 * @property string|null $status
 * @property array|null $config
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Flow $flow
 * @property-read FlowTransition[] $outgoing
 * @property-read FlowTransition[] $incoming
 * @property-read FlowTask[] $tasks
 * @property-read bool $is_start
 * @property-read bool $is_end
 */
class FlowStateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = is_object($this->type) && method_exists($this->type, 'value')
            ? $this->type->value
            : $this->type;

        return [
            'id' => $this->id,
            'flow_id' => $this->flow_id,

            'type' => $type,
            'status' => $this->status,
            'config' => $this->config,

            'is_start' => (bool)($this->is_start ?? ($type === 'start')),
            'is_end' => (bool)($this->is_end ?? ($type === 'end')),

            // ISO 8601 timestamps for interoperability across clients
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Loaded relations
            'flow' => $this->whenLoaded('flow', function () {
                return FlowResource::make($this->flow);
            }),

            'outgoing' => $this->whenLoaded('outgoing', function () {
                return FlowTransitionResource::collection($this->outgoing);
            }),

            'incoming' => $this->whenLoaded('incoming', function () {
                return FlowTransitionResource::collection($this->incoming);
            }),

            'tasks' => $this->whenLoaded('tasks', function () {
                return FlowTaskResource::collection($this->tasks);
            }),
        ];
    }
}
