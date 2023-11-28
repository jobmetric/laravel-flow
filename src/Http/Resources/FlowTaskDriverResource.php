<?php

namespace JobMetric\Flow\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JobMetric\Flow\Contracts\TaskContract;

/**
 * @property mixed id
 * @property mixed driver
 * @property mixed status
 * @property mixed states
 * @property mixed transitions
 */
class FlowTaskDriverResource extends JsonResource
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
        ];
    }
}
