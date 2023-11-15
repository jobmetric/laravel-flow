<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flow extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver',
        'status'
    ];

    protected $casts = [
        'driver' => 'string',
        'status' => 'boolean'
    ];

    public function getTable()
    {
        return config('workflow.tables.flow', parent::getTable());
    }

    public function states(): HasMany
    {
        return $this->hasMany(FlowState::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(FlowTransition::class);
    }
}
