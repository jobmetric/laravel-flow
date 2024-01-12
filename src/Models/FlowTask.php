<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JobMetric\PackageCore\Models\HasBooleanStatus;

/**
 * @method static create(array $data)
 * @method static findOrFail(int $flow_task_id)
 */
class FlowTask extends Model
{
    use HasFactory, HasBooleanStatus;

    protected $fillable = [
        'flow_transition_id',
        'driver',
        'config',
        'ordering',
        'status'
    ];

    protected $casts = [
        'flow_transition_id' => 'integer',
        'driver' => 'string',
        'config' => 'json',
        'ordering' => 'integer',
        'status' => 'boolean'
    ];

    public function getTable()
    {
        return config('workflow.tables.flow_task', parent::getTable());
    }

    public function flowTransition(): BelongsTo
    {
        return $this->belongsTo(FlowTransition::class);
    }
}
