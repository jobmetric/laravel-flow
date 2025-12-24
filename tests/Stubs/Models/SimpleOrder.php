<?php

namespace JobMetric\Flow\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JobMetric\Flow\HasFlow;
use JobMetric\Flow\Tests\Stubs\Enums\OrderStatusEnum;

class SimpleOrder extends Model
{
    use HasFactory, HasFlow;

    protected $fillable = [
        'user_id',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'status'  => OrderStatusEnum::class,
    ];

    protected $table = 'orders';

    /**
     * Temporary storage for flow_id that should not be saved to database.
     */
    private ?int $flowIdTemp = null;

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Remove flow_id from attributes before saving to database
        static::creating(function (self $model): void {
            $flowId = $model->getAttribute('flow_id');
            if ($flowId !== null) {
                // Store flow_id temporarily in private property
                $model->flowIdTemp = (int) $flowId;
                // Remove from attributes so it won't be saved
                unset($model->attributes['flow_id']);
            }
        });

        static::updating(function (self $model): void {
            $flowId = $model->getAttribute('flow_id');
            if ($flowId !== null) {
                // Store flow_id temporarily in private property
                $model->flowIdTemp = (int) $flowId;
                // Remove from attributes so it won't be saved
                unset($model->attributes['flow_id']);
            }
        });

        // Restore flow_id after saving
        static::saved(function (self $model): void {
            if ($model->flowIdTemp !== null) {
                $model->setAttribute('flow_id', $model->flowIdTemp);
                $model->flowIdTemp = null;
            }
        });
    }

    /**
     * Override flowId() to return Flow ID from attribute or config.
     */
    protected function flowId(): ?int
    {
        // First try temp property (during save operations)
        if ($this->flowIdTemp !== null) {
            return $this->flowIdTemp;
        }

        // Then try attribute
        if ($this->getAttribute('flow_id') !== null) {
            return (int) $this->getAttribute('flow_id');
        }

        // Finally try config
        return config('test.flow_id');
    }

    /**
     * Helper method for tests to set flow_id attribute without saving to database.
     */
    public function setFlowId(?int $flowId): self
    {
        $this->setAttribute('flow_id', $flowId);

        return $this;
    }
}
