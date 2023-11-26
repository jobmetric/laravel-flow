<?php

namespace JobMetric\Flow\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed id
 * @property mixed from
 * @property mixed to
 * @property mixed slug
 * @property mixed role_id
 * @property mixed flow
 * @property mixed fromState
 * @property mixed toState
 */
class FlowTransitionResource extends JsonResource
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
            'from' => FlowStateResource::make($this->fromState),
            'to' => FlowStateResource::make($this->toState),
            'slug' => $this->slug,
            'role_id' => $this->role_id,

            'flow' => $this->whenLoaded('flow', function () {
                return FlowResource::make($this->flow);
            }),
        ];
    }
}
