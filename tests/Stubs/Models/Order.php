<?php

namespace JobMetric\Flow\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JobMetric\Flow\HasWorkflow;
use JobMetric\Flow\Support\FlowPickerBuilder;
use JobMetric\Flow\Tests\Stubs\Enums\OrderStatusEnum;
use JobMetric\Flow\Tests\Stubs\Factories\OrderFactory;

/**
 * @method static create(string[] $array)
 */
class Order extends Model
{
    use HasFactory;

    // Alias the trait's method so we can call the original implementation
    use HasWorkflow {
        buildFlowPicker as protected traitBuildFlowPicker;
    }

    protected $fillable = [
        'user_id',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'status' => OrderStatusEnum::class,
    ];

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    /**
     * Tune FlowPickerBuilder for Order model in tests.
     *
     * - Subject type is set by the trait.
     * - We use user_id as subject scope and rollout key to make selection deterministic.
     * - We prefer test/web to make ordering deterministic in tests.
     */
    protected function buildFlowPicker(FlowPickerBuilder $builder): void
    {
        $this->traitBuildFlowPicker($builder);

        $builder
            ->subjectScope($this->user_id ? (string)$this->user_id : null)
            ->environment('test')
            ->channel('web')
            ->preferEnvironments(['test', 'staging', 'prod'])
            ->preferChannels(['web', 'api'])
            ->rolloutNamespace('order')
            ->rolloutSalt('tests')
            ->rolloutKeyResolver(function (Model $m): ?string {
                $uid = $m->getAttribute('user_id');

                return $uid === null ? null : (string)$uid;
            })
            ->fallbackCascade([
                FlowPickerBuilder::FB_DROP_CHANNEL,
                FlowPickerBuilder::FB_DROP_ENVIRONMENT,
                FlowPickerBuilder::FB_IGNORE_TIMEWINDOW,
            ]);
    }
}
