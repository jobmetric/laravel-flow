<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlowState extends Model
{
    use HasFactory;

    public function getTable()
    {
        return config('workflow.tables.flow_state', parent::getTable());
    }
}
