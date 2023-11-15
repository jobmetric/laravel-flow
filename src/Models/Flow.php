<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Flow extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver',
        'status'
    ];

    public function getTable()
    {
        return config('workflow.tables.flow', parent::getTable());
    }
}
