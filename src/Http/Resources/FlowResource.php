<?php

namespace JobMetric\Flow\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed id
 * @property mixed driver
 * @property mixed status
 * @property mixed states
 * @property mixed transitions
 */
class FlowResource extends JsonResource
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
            'status' => $this->status,

            'states' => $this->whenLoaded('states', function () {
                return FlowStateResource::collection($this->states);
            }),

            'transitions' => $this->whenLoaded('transitions', function () {
                return FlowTransitionResource::collection($this->transitions);
            }),
        ];
    }
}
