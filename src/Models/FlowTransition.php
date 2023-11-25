<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static ofSlug(string $slug)
 * @method static ofFrom(int $from)
 * @method static ofTo(int $from)
 * @method static findOrFail(int $flow_transition_id)
 */
class FlowTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'flow_id',
        'from',
        'to',
        'slug',
        'role_id'
    ];

    protected $casts = [
        'flow_id' => 'integer',
        'from' => 'integer',
        'to' => 'integer',
        'slug' => 'string',
        'role_id' => 'integer'
    ];

    public function getTable()
    {
        return config('workflow.tables.flow_transition', parent::getTable());
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    public function fromState(): BelongsTo
    {
        return $this->belongsTo(FlowState::class, 'from');
    }

    public function toState(): BelongsTo
    {
        return $this->belongsTo(FlowState::class, 'to');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(config('workflow.models.role'));
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(FlowTask::class);
    }

    /**
     * Scope a query to only include flow transition of a given from.
     *
     * @param Builder $query
     * @param int $from
     *
     * @return Builder
     */
    public function scopeOfFrom(Builder $query, int $from): Builder
    {
        return $query->where('from', $from);
    }

    /**
     * Scope a query to only include flow transition of a given to.
     *
     * @param Builder $query
     * @param int $to
     *
     * @return Builder
     */
    public function scopeOfTo(Builder $query, int $to): Builder
    {
        return $query->where('to', $to);
    }

    /**
     * Scope a query to only include flow transition of a given slug.
     *
     * @param Builder $query
     * @param string $slug
     *
     * @return Builder
     */
    public function scopeOfSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }
}
