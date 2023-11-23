<?php

namespace JobMetric\Flow\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed id
 * @property mixed from
 * @property mixed to
 * @property mixed slug
 * @property mixed flow
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
            'from' => $this->from,
            'to' => $this->to,
            'slug' => $this->slug,

            'flow' => $this->whenLoaded('flow', function () {
                return FlowResource::make($this->flow);
            }),
        ];
    }
}
