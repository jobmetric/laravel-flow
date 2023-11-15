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

    public function getTable()
    {
        return config('workflow.tables.flow_task', parent::getTable());
    }
}
