<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlowTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'flow_id',
        'from',
        'to',
        'slug',
        'roll_id'
    ];

    protected $casts = [
        'flow_id' => 'integer',
        'from' => 'integer',
        'to' => 'integer',
        'slug' => 'string',
        'roll_id' => 'integer'
    ];

    public function getTable()
    {
        return config('workflow.tables.flow_transition', parent::getTable());
    }
}
