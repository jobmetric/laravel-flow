<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowState extends Model
{
    use HasFactory;

    protected $fillable = [
        'flow_id',
        'type',
        'config',
        'status'
    ];

    protected $casts = [
        'flow_id' => 'integer',
        'type' => 'string',
        'config' => 'json',
        'status' => 'string'
    ];

    public function getTable()
    {
        return config('workflow.tables.flow_state', parent::getTable());
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }
}
