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
class FlowTaskListResource extends JsonResource
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
            'class'       => class_basename($this->resource),
        ];
    }
}
