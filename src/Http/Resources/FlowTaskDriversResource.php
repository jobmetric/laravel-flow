<?php

namespace JobMetric\Flow\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlowTaskDriversResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this['key'],
            'title' => $this['title'],
            'task' => FlowTaskDetailResource::collection($this['tasks']),
        ];
    }
}
