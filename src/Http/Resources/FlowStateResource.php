<?php

namespace JobMetric\Flow\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed id
 * @property mixed type
 * @property mixed config
 * @property mixed status
 * @property mixed flow
 */
class FlowStateResource extends JsonResource
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
            'type' => $this->type,
            'config' => $this->config,
            'status' => $this->status,

            'flow' => $this->whenLoaded('flow', function () {
                return FlowResource::make($this->flow);
            }),
        ];
    }
}
