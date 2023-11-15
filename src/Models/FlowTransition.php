<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlowTransition extends Model
{
    use HasFactory;

    public function getTable()
    {
        return config('workflow.tables.flow_transition', parent::getTable());
    }
}
