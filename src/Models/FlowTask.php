<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlowTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'flow_id',
        'driver',
        'config',
        'ordering',
        'status'
    ];

    protected $casts = [
        'flow_id' => 'integer',
        'driver' => 'string',
        'config' => 'json',
        'ordering' => 'integer',
        'status' => 'boolean'
    ];

    public function getTable()
    {
        return config('workflow.tables.flow_task', parent::getTable());
    }
}
