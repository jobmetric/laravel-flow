<?php

namespace JobMetric\Flow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

    public function assetable(): MorphTo
    {
        return $this->morphTo();
    }

    public function flowState(): BelongsTo
    {
        return $this->belongsTo(FlowState::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('workflow.models.user'));
    }
}
