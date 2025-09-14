<?php

namespace JobMetric\Flow\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use JobMetric\Flow\Models\Flow;

/**
 * Class FlowUseResource
 *
 * Transforms the FlowUse model into a structured JSON resource.
 *
 * @property int $id
 * @property int $flow_id
 * @property string $flowable_type
 * @property int $flowable_id
 * @property Carbon $used_at
 *
 * @property-read Flow $flow
 * @property-read Model $flowable
 */
class FlowUseResource extends JsonResource
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
            'flow_id' => $this->flow_id,
            'flowable_type' => $this->flowable_type,
            'flowable_id' => $this->flowable_id,

            // ISO 8601 timestamps for interoperability across clients
            'used_at' => $this->used_at?->toISOString(),

            // Loaded relations
            'flow' => $this->whenLoaded('flow', function () {
                return FlowResource::make($this->flow);
            }),

            'flowable' => $this->whenLoaded('flowable', function () {
                // Unknown concrete resource type => wrap as generic JsonResource
                return JsonResource::make($this->flowable);
            }),
        ];
    }
}
