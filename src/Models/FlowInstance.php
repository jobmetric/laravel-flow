<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use JobMetric\PackageCore\Traits\HasMorphResourceAttributes;

/**
 * Class FlowInstance
 *
 * Represents a workflow instance snapshot associated with any Eloquent model
 * via a polymorphic relation. It keeps track of the current transition
 * (which implies the current state) and the actor responsible for that change.
 *
 * This model is designed to record the runtime position of an entity inside
 * a flow without storing full history (history should live elsewhere).
 *
 * @package JobMetric\Flow
 *
 * @property int $id The primary identifier of the instance row.
 * @property string $instanceable_type The class name of the related model.
 * @property int $instanceable_id The ID of the related model instance.
 * @property int $flow_transition_id The current transition identifier.
 * @property string|null $actor_type The class name of the actor model (nullable).
 * @property int|null $actor_id The ID of the actor model instance (nullable).
 * @property Carbon $started_at The timestamp when this instance started.
 * @property Carbon|null $completed_at The timestamp when this instance completed.
 *
 * @property-read Model|MorphTo $instanceable
 * @property-read Model|MorphTo|null $actor
 * @property-read FlowTransition $transition
 * @property-read Flow|null $flow
 * @property-read FlowState|null $current_state
 * @property-read string|null $current_status
 * @property-read bool $is_active
 * @property-read int|null $duration_seconds
 * @property-read mixed $instanceable_resource
 * @property-read mixed $actor_resource
 *
 * @method static Builder|FlowInstance whereInstanceableType(string $instanceable_type)
 * @method static Builder|FlowInstance whereInstanceableId(int $instanceable_id)
 * @method static Builder|FlowInstance whereFlowTransitionId(int $flow_transition_id)
 * @method static Builder|FlowInstance whereActorType(?string $actor_type)
 * @method static Builder|FlowInstance whereActorId(?int $actor_id)
 * @method static Builder|FlowInstance active()
 * @method static Builder|FlowInstance completed()
 * @method static Builder|FlowInstance forInstanceable(string $type, int $id)
 * @method static Builder|FlowInstance forModel(Model $model)
 * @method static Builder|FlowInstance forTransition(int $transitionId)
 * @method static Builder|FlowInstance byActor(string $actorType, int $actorId)
 * @method static Builder|FlowInstance startedBetween(Carbon|string $from, Carbon|string $to)
 * @method static Builder|FlowInstance latest()
 */
class FlowInstance extends Model
{
    use HasFactory,
        HasMorphResourceAttributes;

    /**
     * This table does not have Laravel's created_at/updated_at columns.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Touch the parent transition when this instance changes.
     *
     * @var array<int, string>
     */
    protected $touches = ['transition'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'instanceable_type',
        'instanceable_id',
        'flow_transition_id',
        'actor_type',
        'actor_id',
        'started_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'instanceable_type' => 'string',
        'instanceable_id' => 'integer',
        'flow_transition_id' => 'integer',
        'actor_type' => 'string',
        'actor_id' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Override the table name using config.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('workflow.tables.flow_instances', parent::getTable());
    }

    /**
     * Get the parent instanceable model (morph-to relation).
     *
     * @return MorphTo
     */
    public function instanceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the actor model (morph-to relation).
     *
     * @return MorphTo
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the current transition.
     *
     * @return BelongsTo
     */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(FlowTransition::class, 'flow_transition_id');
    }

    /**
     * Computed accessor to the owning flow via transition (NOT a direct relation).
     *
     * @return Flow|null
     */
    public function getFlowAttribute(): ?Flow
    {
        if ($this->relationLoaded('transition')) {
            /** @var FlowTransition|null $t */
            $t = $this->getRelation('transition');

            return $t?->relationLoaded('flow') ? $t->getRelation('flow') : $t?->flow()->first();
        }

        $transition = $this->transition()->first();

        return $transition?->flow()->first();
    }

    /**
     * Convenience accessor: current state (toState if set, otherwise fromState).
     *
     * @return FlowState|null
     */
    public function getCurrentStateAttribute(): ?FlowState
    {
        $t = $this->relationLoaded('transition') ? $this->getRelation('transition') : $this->transition()->with(['toState', 'fromState'])->first();
        if (!$t) {
            return null;
        }

        return $t->toState ?: $t->fromState;
    }

    /**
     * Convenience accessor: domain status of current state.
     *
     * @return string|null
     */
    public function getCurrentStatusAttribute(): ?string
    {
        return $this->current_state?->status;
    }

    /**
     * Convenience accessor: is this instance active (not completed)?
     *
     * @return bool
     */
    public function getIsActiveAttribute(): bool
    {
        return is_null($this->completed_at);
    }

    /**
     * Convenience accessor: duration in seconds since started_at
     * (until completed_at if set, otherwise until now).
     *
     * @return int|null
     */
    public function getDurationSecondsAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $end = $this->completed_at ?? Carbon::now();

        return $end->diffInSeconds($this->started_at);
    }

    /**
     * Scope: only active instances (not completed).
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    /**
     * Scope: only completed instances.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * Scope: filter by instanceable pair.
     *
     * @param Builder $query
     * @param string $type
     * @param int $id
     *
     * @return Builder
     */
    public function scopeForInstanceable(Builder $query, string $type, int $id): Builder
    {
        return $query->where([
            'instanceable_type' => $type,
            'instanceable_id' => $id
        ]);
    }

    /**
     * Scope: filter by a concrete Eloquent model instance.
     *
     * @param Builder $query
     * @param Model $model
     *
     * @return Builder
     */
    public function scopeForModel(Builder $query, Model $model): Builder
    {
        return $this->scopeForInstanceable($query, $model->getMorphClass(), $model->getKey());
    }

    /**
     * Scope: filter by transition id.
     *
     * @param Builder $query
     * @param int $transitionId
     *
     * @return Builder
     */
    public function scopeForTransition(Builder $query, int $transitionId): Builder
    {
        return $query->where('flow_transition_id', $transitionId);
    }

    /**
     * Scope: filter by actor pair.
     *
     * @param Builder $query
     * @param string $actorType
     * @param int $actorId
     *
     * @return Builder
     */
    public function scopeByActor(Builder $query, string $actorType, int $actorId): Builder
    {
        return $query->where([
            'actor_type' => $actorType,
            'actor_id' => $actorId
        ]);
    }

    /**
     * Scope: started within a time window.
     *
     * @param Builder $query
     * @param Carbon|string $from
     * @param Carbon|string $to
     *
     * @return Builder
     */
    public function scopeStartedBetween(Builder $query, Carbon|string $from, Carbon|string $to): Builder
    {
        return $query->whereBetween('started_at', [$from, $to]);
    }

    /**
     * Scope: latest first by started_at (then id fallback).
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderByDesc('started_at')->orderByDesc('id');
    }
}
