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
class FlowTaskDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title'       => $this->getTitle(),
            'description' => $this->getDescription(),
            'is_global'   => $this->isGlobal(),
            'fields'      => $this->getFields(),
        ];
    }
}
