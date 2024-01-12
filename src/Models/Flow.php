<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use JobMetric\PackageCore\Models\HasBooleanStatus;

/**
 * @method static create(array $data)
 * @method static findOrFail(int $flow_id)
 * @property int id
 * @property string driver
 * @property boolean status
 */
class Flow extends Model
{
    use HasFactory, SoftDeletes, HasBooleanStatus;

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
