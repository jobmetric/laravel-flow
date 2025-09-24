<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use JobMetric\PackageCore\Models\HasBooleanStatus;

/**
 * Class FlowTask
 *
 * Represents a task attached to a transition in the workflow. A task can be a
 * restriction, validation, or action that runs (typically) when the transition
 * is executed. Tasks are ordered within the transition and can be enabled/disabled.
 *
 * @package JobMetric\Flow
 *
 * @property int $id The primary identifier of the flow task row.
 * @property int $flow_transition_id The owning transition identifier.
 * @property string $driver The task driver class/name.
 * @property array|null $config Optional JSON configuration for the task.
 * @property int $ordering Execution/display ordering within the transition.
 * @property bool $status Active flag for the task (true=enabled, false=disabled).
 * @property Carbon $created_at The timestamp when this task was created.
 * @property Carbon $updated_at The timestamp when this task was last updated.
 *
 * @property-read FlowTransition $transition
 * @property-read Flow|null $flow
 *
 * @method static Builder|FlowTask whereFlowTransitionId(int $flow_transition_id)
 * @method static Builder|FlowTask whereDriver(string $driver)
 * @method static Builder|FlowTask whereOrdering(int $ordering)
 * @method static Builder|FlowTask whereStatus(bool $status)
 * @method static Builder|FlowTask forTransition(int $transitionId)
 * @method static Builder|FlowTask ordered()
 * @method static Builder|FlowTask driver(string $driver)
 */
class FlowTask extends Model
{
    use HasFactory,
        HasBooleanStatus;

    /**
     * Touch the parent transition when this task changes.
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
        'flow_transition_id',
        'driver',
        'config',
        'ordering',
        'status',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'flow_transition_id' => 'integer',
        'driver' => 'string',
        'config' => AsArrayObject::class,
        'ordering' => 'integer',
        'status' => 'boolean',
    ];

    /**
     * Override the table name using config.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('workflow.tables.flow_task', parent::getTable());
    }

    /**
     * Model boot hooks.
     *
     * @return void
     */
    protected static function booted(): void
    {
        // Auto-assign ordering if not provided (append to end of the transition's list).
        static::creating(function (self $task): void {
            if ($task->ordering === null) {
                $max = static::where('flow_transition_id', $task->flow_transition_id)->max('ordering');
                $task->ordering = is_null($max) ? 0 : ($max + 1);
            }
        });
    }

    /**
     * Normalize driver on set (trim & normalize namespace slashes).
     *
     * @param string $value
     */
    public function setDriverAttribute(string $value): void
    {
        // convert forward slashes to backslashes and trim spaces
        $this->attributes['driver'] = trim(str_replace('/', '\\', $value));
    }

    /**
     * Get the owning transition.
     *
     * @return BelongsTo
     */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(FlowTransition::class, 'flow_transition_id');
    }

    /**
     * Computed accessor to owning flow via transition (NOT an Eloquent relation).
     *
     * @return Flow|null
     */
    public function getFlowAttribute(): ?Flow
    {
        if ($this->relationLoaded('transition')) {
            /** @var FlowTransition|null $transition */
            $transition = $this->getRelation('transition');

            return $transition?->relationLoaded('flow') ? $transition->getRelation('flow') : $transition?->flow()->first();
        }

        $transition = $this->transition()->first();

        return $transition?->flow()->first();
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
     * Scope: default ordering within transition (ascending).
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('ordering');
    }

    /**
     * Scope: filter by driver exact match.
     *
     * @param Builder $query
     * @param string $driver
     *
     * @return Builder
     */
    public function scopeDriver(Builder $query, string $driver): Builder
    {
        return $query->where('driver', $driver);
    }
}
