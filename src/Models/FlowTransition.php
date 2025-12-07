<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Translation\HasTranslation;

/**
 * Class FlowTransition
 *
 * Represents a directed edge between two states of a flow.
 * Special cases:
 * - Start edge: from = NULL, to = <start_state_id>
 * - End edge:   from = <end_state_id>, to = NULL
 *
 * @package JobMetric\Flow
 *
 * @property int $id            The primary identifier of the transition row.
 * @property int $flow_id       The owning flow identifier.
 * @property int|null $from     Source state id (nullable for start edge).
 * @property int|null $to       Destination state id (nullable for end edge).
 * @property string|null $slug  Optional transition identifier (unique per flow when not null).
 * @property Carbon $created_at The timestamp when this transition was created.
 * @property Carbon $updated_at The timestamp when this transition was last updated.
 *
 * @property-read Flow $flow
 * @property-read FlowState|null $fromState
 * @property-read FlowState|null $toState
 * @property-read FlowTask[] $tasks
 * @property-read FlowInstance[] $instances
 * @property-read bool $is_start_edge
 * @property-read bool $is_end_edge
 * @property-read bool $is_start_transition
 * @property-read bool $is_specific_transition
 * @property-read bool $is_self_loop_transition
 * @property-read bool $is_generic_input_transition
 * @property-read bool $is_generic_output_transition
 *
 * @method static Builder|FlowTransition whereFlowId(int $flow_id)
 * @method static Builder|FlowTransition whereFrom(?int $from)
 * @method static Builder|FlowTransition whereTo(?int $to)
 * @method static Builder|FlowTransition whereSlug(?string $slug)
 * @method static Builder|FlowTransition startEdges()
 * @method static Builder|FlowTransition endEdges()
 * @method static Builder|FlowTransition between(int $fromId, int $toId)
 * @method static Builder|FlowTransition withSlug(string $slug)
 * @method static Builder|FlowTransition startTransitions()
 * @method static Builder|FlowTransition specificTransitions()
 * @method static Builder|FlowTransition selfLoopTransitions()
 * @method static Builder|FlowTransition genericInputTransitions()
 * @method static Builder|FlowTransition genericOutputTransitions()
 */
class FlowTransition extends Model
{
    use HasFactory, HasTranslation;

    /**
     * Touch the parent flow when this transition changes.
     *
     * @var array<int, string>
     */
    protected $touches = ['flow'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'flow_id',
        'from',
        'to',
        'slug',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'flow_id' => 'integer',
        'from'    => 'integer',
        'to'      => 'integer',
        'slug'    => 'string',
    ];

    /**
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    protected array $translatables = ['name'];

    /**
     * Override the table name using config.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('workflow.tables.flow_transition', parent::getTable());
    }

    /**
     * Get the owning flow.
     *
     * @return BelongsTo
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class, 'flow_id');
    }

    /**
     * Get the source state.
     *
     * @return BelongsTo
     */
    public function fromState(): BelongsTo
    {
        return $this->belongsTo(FlowState::class, 'from');
    }

    /**
     * Get the destination state.
     *
     * @return BelongsTo
     */
    public function toState(): BelongsTo
    {
        return $this->belongsTo(FlowState::class, 'to');
    }

    /**
     * Get tasks attached to this transition.
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(FlowTask::class, 'flow_transition_id');
    }

    /**
     * Get instances currently at this transition.
     *
     * @return HasMany
     */
    public function instances(): HasMany
    {
        return $this->hasMany(FlowInstance::class, 'flow_transition_id');
    }

    /**
     * Accessor: is this a start edge?
     *
     * @return bool
     */
    public function getIsStartEdgeAttribute(): bool
    {
        return is_null($this->from) && ! is_null($this->to);
    }

    /**
     * Accessor: is this an end edge?
     *
     * @return bool
     */
    public function getIsEndEdgeAttribute(): bool
    {
        return ! is_null($this->from) && is_null($this->to);
    }

    /**
     * Accessor: is this a start transition? (from start state)
     *
     * @return bool
     */
    public function getIsStartTransitionAttribute(): bool
    {
        if (is_null($this->from)) {
            return false;
        }

        $fromState = $this->fromState;
        if (! $fromState) {
            return false;
        }

        return $fromState->is_start;
    }

    /**
     * Accessor: is this a specific transition? (both from and to are set and not equal)
     *
     * @return bool
     */
    public function getIsSpecificTransitionAttribute(): bool
    {
        return ! is_null($this->from) && ! is_null($this->to) && $this->from !== $this->to;
    }

    /**
     * Accessor: is this a self-loop transition? (from equals to)
     *
     * @return bool
     */
    public function getIsSelfLoopTransitionAttribute(): bool
    {
        return ! is_null($this->from) && ! is_null($this->to) && $this->from === $this->to;
    }

    /**
     * Accessor: is this a generic input transition? (from is null)
     *
     * @return bool
     */
    public function getIsGenericInputTransitionAttribute(): bool
    {
        return is_null($this->from) && ! is_null($this->to);
    }

    /**
     * Accessor: is this a generic output transition? (to is null)
     *
     * @return bool
     */
    public function getIsGenericOutputTransitionAttribute(): bool
    {
        return ! is_null($this->from) && is_null($this->to);
    }

    /**
     * Scope: only start edges (from IS NULL).
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeStartEdges(Builder $query): Builder
    {
        return $query->whereNull('from');
    }

    /**
     * Scope: only end edges (to IS NULL).
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeEndEdges(Builder $query): Builder
    {
        return $query->whereNull('to');
    }

    /**
     * Scope: edge between two concrete states.
     *
     * @param Builder $query
     * @param int $fromId
     * @param int $toId
     *
     * @return Builder
     */
    public function scopeBetween(Builder $query, int $fromId, int $toId): Builder
    {
        return $query->where([
            'from' => $fromId,
            'to'   => $toId,
        ]);
    }

    /**
     * Scope: filter by slug (non-null).
     *
     * @param Builder $query
     * @param string $slug
     *
     * @return Builder
     */
    public function scopeWithSlug(Builder $query, string $slug): Builder
    {
        return $query->whereNotNull('slug')->where('slug', $slug);
    }

    /**
     * Scope: only start transitions (from start state).
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeStartTransitions(Builder $query): Builder
    {
        return $query->whereHas('fromState', function ($q) {
            $q->where('type', FlowStateTypeEnum::START());
        });
    }

    /**
     * Scope: only specific transitions (both from and to are set and not equal).
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeSpecificTransitions(Builder $query): Builder
    {
        return $query->whereNotNull('from')->whereNotNull('to')->whereColumn('from', '!=', 'to');
    }

    /**
     * Scope: only self-loop transitions (from equals to).
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeSelfLoopTransitions(Builder $query): Builder
    {
        return $query->whereNotNull('from')->whereNotNull('to')->whereColumn('from', '=', 'to');
    }

    /**
     * Scope: only generic input transitions (from is null).
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeGenericInputTransitions(Builder $query): Builder
    {
        return $query->whereNull('from')->whereNotNull('to');
    }

    /**
     * Scope: only generic output transitions (to is null).
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeGenericOutputTransitions(Builder $query): Builder
    {
        return $query->whereNotNull('from')->whereNull('to');
    }
}
