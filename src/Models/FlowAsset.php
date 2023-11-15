<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlowAsset extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'assetable_id',
        'assetable_type',
        'flow_state_id',
        'user_id'
    ];

    protected $casts = [
        'assetable_id' => 'integer',
        'assetable_type' => 'string',
        'flow_state_id' => 'integer',
        'user_id' => 'integer'
    ];

    public function getTable()
    {
        return config('workflow.tables.flow_asset', parent::getTable());
    }
}
