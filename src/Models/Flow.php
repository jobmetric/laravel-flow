<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method static create(array $data)
 * @method static findOrFail(int $flow_id)
 * @property mixed driver
 * @property mixed status
 */
class Flow extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'driver',
        'status'
    ];

    protected $casts = [
        'driver' => 'string',
        'status' => 'boolean'
    ];

    public function getTable()
    {
        return config('workflow.tables.flow', parent::getTable());
    }

    public function states(): HasMany
    {
        return $this->hasMany(FlowState::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(FlowTransition::class);
    }

    /**
     * Scope active.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * Scope inactive.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', false);
    }
}
