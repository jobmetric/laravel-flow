<?php

namespace JobMetric\Flow\Tests\Stubs\Tasks;

use JobMetric\CustomField\CustomFieldBuilder;
use JobMetric\Flow\Contracts\AbstractValidationTask;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskDefinition;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Form\FormBuilder;
use Throwable;

/**
 * Stub validation task driver for testing FlowTask service.
 */
class DummyValidationTask extends AbstractValidationTask
{
    /**
     * @inheritDoc
     */
    public static function subject(): string
    {
        return Order::class;
    }

    /**
     * @inheritDoc
     */
    public static function definition(): FlowTaskDefinition
    {
        return new FlowTaskDefinition(
            title: 'Dummy Validation Task',
            description: 'A dummy validation task for testing purposes.',
        );
    }

    /**
     * @inheritDoc
     * @throws Throwable
     */
    public function form(): FormBuilder
    {
        return (new FormBuilder)
            ->tab(function ($tab) {
                $tab->id('settings')->label('Settings')->startPosition()
                    ->customField(function (CustomFieldBuilder $fieldBuilder) {
                        $fieldBuilder::number()
                            ->name('min_amount')
                            ->label('Minimum Amount')
                            ->validation('required|numeric|min:0');
                    });
            });
    }

    /**
     * @inheritDoc
     */
    public function rules(FlowTaskContext $context): array
    {
        // Dummy implementation for testing
        return [
            'amount' => 'required|numeric|min:' . ($context->config()['min_amount'] ?? 0),
        ];
    }
}
