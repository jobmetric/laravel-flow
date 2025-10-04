<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Translation\HasTranslation;

/**
 * Class FlowState
 *
 * Represents a state (node) inside a workflow definition. The "type" field
 * indicates whether the state is a starting node, a normal state, or an ending node.
 * Expected values for "type": start | state.
 *
 * @package JobMetric\Flow
 *
 * @property int $id The primary identifier of the flow state row.
 * @property int $flow_id The owning flow identifier.
 * @property FlowStateTypeEnum|string $type The state type: start | state.
 * @property object|null $config Optional UI/behavior configuration (JSON).
 * @property string|null $status Optional domain status key mapped to the subject model.
 * @property Carbon $created_at The timestamp when this state was created.
 * @property Carbon $updated_at The timestamp when this state was last updated.
 *
 * @property-read Flow $flow
 * @property-read FlowTransition[] $outgoing
 * @property-read FlowTransition[] $incoming
 * @property-read FlowTask[] $tasks
 * @property-read bool $is_start
 * @property-read bool $is_end
 *
 * @method static Builder|FlowState whereFlowId(int $flow_id)
 * @method static Builder|FlowState whereType(string $type)
 * @method static Builder|FlowState whereStatus(?string $status)
 * @method static Builder|FlowState ofType(FlowStateTypeEnum|string $type)
 * @method static Builder|FlowState start()
 * @method static Builder|FlowState end()
 */
class FlowState extends Model
{
    use HasFactory,
        HasTranslation;

    /**
     * Touch the parent flow when this state is updated.
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
        'type',
        'config',
        'status',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'flow_id' => 'integer',
        'type' => FlowStateTypeEnum::class,
        'config' => AsArrayObject::class,
        'status' => 'string',
    ];

    /**
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    protected array $translatables = ['name', 'description'];

    /**
     * Override the table name using config.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('workflow.tables.flow_state', parent::getTable());
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
     * Get transitions originating from this state.
     *
     * @return HasMany
     */
    public function outgoing(): HasMany
    {
        return $this->hasMany(FlowTransition::class, 'from');
    }

    /**
     * Get transitions pointing to this state.
     *
     * @return HasMany
     */
    public function incoming(): HasMany
    {
        return $this->hasMany(FlowTransition::class, 'to');
    }

    /**
     * Get tasks reachable from this state via outgoing transitions.
     *
     * @return HasManyThrough
     */
    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(FlowTask::class, FlowTransition::class, 'from', 'flow_transition_id', 'id', 'id');
    }

    /**
     * Accessor: is this a start state?
     *
     * @return bool
     */
    public function getIsStartAttribute(): bool
    {
        return ($this->type instanceof FlowStateTypeEnum ? $this->type->value : $this->type) === 'start';
    }

    /**
     * Accessor: is this an end state?
     *
     * @return bool
     */
    public function getIsEndAttribute(): bool
    {
        return (bool)$this->config?->is_terminal === true;
    }

    /**
     * Scope: filter by type (accepts enum or string).
     *
     * @param Builder $query
     * @param FlowStateTypeEnum|string $type
     *
     * @return Builder
     */
    public function scopeOfType(Builder $query, FlowStateTypeEnum|string $type): Builder
    {
        return $query->where('type', $type instanceof FlowStateTypeEnum ? $type->value : $type);
    }

    /**
     * Scope: only start states.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeStart(Builder $query): Builder
    {
        return $this->scopeOfType($query, FlowStateTypeEnum::START());
    }

    /**
     * Scope: only end states.
     *
     * @param Builder $query
     *
     * @return Builder|FlowState
     */
    public function scopeEnd(Builder $query): FlowState|Builder
    {
        return $this->whereJsonContains('config->is_terminal', true);
    }
}
