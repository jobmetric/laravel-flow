<?php

namespace JobMetric\Flow\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Flow\Models\FlowTransition;

/**
 * Class FlowInstanceResource
 *
 * Transforms the FlowInstance model into a structured JSON resource.
 *
 * @property int $id
 * @property string $instanceable_type
 * @property int $instanceable_id
 * @property int $flow_transition_id
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 *
 * @property-read Model|MorphTo $instanceable
 * @property-read Model|MorphTo|null $actor
 * @property-read FlowTransition $transition
 * @property-read Flow|null $flow
 * @property-read FlowState|null $current_state
 * @property-read string|null $current_status
 * @property-read bool $is_active
 * @property-read int|null $duration_seconds
 */
class FlowInstanceResource extends JsonResource
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
            'instanceable_type' => $this->instanceable_type,
            'instanceable_id' => $this->instanceable_id,
            'flow_transition_id' => $this->flow_transition_id,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,

            // ISO 8601 timestamps for interoperability across clients
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),

            // Convenience fields from accessors (if defined on the model)
            'current_status' => $this->current_status ?? null,
            'is_active' => (bool)($this->is_active ?? is_null($this->completed_at)),
            'duration_seconds' => $this->duration_seconds ?? null,

            // Loaded relations
            'instanceable' => $this->whenLoaded('instanceable', function () {
                // Unknown concrete resource type => wrap as generic JsonResource
                return JsonResource::make($this->instanceable);
            }),

            'actor' => $this->whenLoaded('actor', function () {
                // Unknown concrete resource type => wrap as generic JsonResource
                return JsonResource::make($this->actor);
            }),

            'transition' => $this->whenLoaded('transition', function () {
                return FlowTransitionResource::make($this->transition);
            }),

            // Expose current_state via transition when it's eagerly loaded (and optionally its nested state)
            'current_state' => $this->whenLoaded('transition', function () {
                $t = $this->transition;

                // Prefer toState when present; otherwise fall back to fromState.
                // Avoid triggering additional queries by checking relationLoaded().
                $state = null;
                if ($t->relationLoaded('toState') || $t->relationLoaded('fromState')) {
                    $state = $t->toState ?? $t->fromState;
                }

                return $state ? FlowStateResource::make($state) : null;
            }),
        ];
    }
}
