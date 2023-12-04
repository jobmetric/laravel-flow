<?php

namespace JobMetric\Flow\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed id
 * @property mixed driver
 * @property mixed config
 * @property mixed ordering
 * @property mixed status
 * @property mixed flowTransition
 */
class FlowTaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver' => $this->driver,
            'config' => $this->config,
            'ordering' => $this->ordering,
            'status' => $this->status,

            'flow_transition' => $this->whenLoaded('flow_transition', function () {
                return FlowTransitionResource::make($this->flowTransition);
            }),
        ];
    }
}
