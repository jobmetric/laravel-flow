<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlowState extends Model
{
    use HasFactory;

    protected $fillable = [
        'flow_id',
        'type',
        'config',
        'status'
    ];

    public function getTable()
    {
        return config('workflow.tables.flow_state', parent::getTable());
    }
}
