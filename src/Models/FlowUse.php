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
 * Class FlowUse
 *
 * Represents a permanent binding between a flow definition and any Eloquent model
 * via a polymorphic relation. This allows associating a specific flow with a
 * given entity (order, invoice, ticket, etc.) without altering the entity's table.
 *
 * @package JobMetric\Flow
 *
 * @property int $id The primary identifier of the binding row.
 * @property int $flow_id The bound flow identifier.
 * @property string $flowable_type The class name of the bound model.
 * @property int $flowable_id The ID of the bound model instance.
 * @property Carbon $used_at The timestamp when this binding was created.
 *
 * @property-read Flow $flow
 * @property-read Model|MorphTo $flowable
 * @property-read mixed $flowable_resource
 *
 * @method static Builder|FlowUse whereFlowId(int $flow_id)
 * @method static Builder|FlowUse whereFlowableType(string $flowable_type)
 * @method static Builder|FlowUse whereFlowableId(int $flowable_id)
 * @method static Builder|FlowUse forFlow(int $flow_id)
 * @method static Builder|FlowUse forFlowable(string $type, int $id)
 * @method static Builder|FlowUse forModel(Model $model)
 * @method static Builder|FlowUse usedBetween(Carbon|string $from, Carbon|string $to)
 * @method static Builder|FlowUse latest()
 */
class FlowUse extends Model
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
     * Touch the parent flow when this binding changes.
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
        'flowable_type',
        'flowable_id',
        'used_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'flow_id' => 'integer',
        'flowable_type' => 'string',
        'flowable_id' => 'integer',
        'used_at' => 'datetime',
    ];

    /**
     * Initialize model events.
     */
    protected static function booted(): void
    {
        // Ensure used_at is set by application layer as well (DB has default too).
        static::creating(function (self $binding): void {
            if (empty($binding->used_at)) {
                $binding->used_at = now();
            }
        });
    }

    /**
     * Override the table name using config.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('workflow.tables.flow_uses', parent::getTable());
    }

    /**
     * Get the related flow definition.
     *
     * @return BelongsTo
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class, 'flow_id');
    }

    /**
     * Get the bound model (polymorphic).
     *
     * @return MorphTo
     */
    public function flowable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope: filter by flow id.
     *
     * @param Builder $query
     * @param int $flowId
     *
     * @return Builder
     */
    public function scopeForFlow(Builder $query, int $flowId): Builder
    {
        return $query->where('flow_id', $flowId);
    }

    /**
     * Scope: filter by flowable pair.
     *
     * @param Builder $query
     * @param string $type
     * @param int $id
     *
     * @return Builder
     */
    public function scopeForFlowable(Builder $query, string $type, int $id): Builder
    {
        return $query->where([
            'flowable_type' => $type,
            'flowable_id' => $id,
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
        return $this->scopeForFlowable($query, $model->getMorphClass(), $model->getKey());
    }

    /**
     * Scope: used within a time window.
     *
     * @param Builder $query
     * @param Carbon|string $from
     * @param Carbon|string $to
     *
     * @return Builder
     */
    public function scopeUsedBetween(Builder $query, Carbon|string $from, Carbon|string $to): Builder
    {
        return $query->whereBetween('used_at', [$from, $to]);
    }

    /**
     * Scope: latest first by used_at (then id fallback).
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderByDesc('used_at')->orderByDesc('id');
    }
}
