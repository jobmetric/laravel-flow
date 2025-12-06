<?php

namespace JobMetric\Flow\Tests\Stubs\Tasks;

use JobMetric\CustomField\CustomFieldBuilder;
use JobMetric\Flow\Contracts\AbstractActionTask;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskDefinition;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Form\FormBuilder;
use Throwable;

/**
 * Stub action task driver for testing FlowTask service.
 */
class DummyActionTask extends AbstractActionTask
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
            title: 'Dummy Action Task',
            description: 'A dummy action task for testing purposes.',
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
                $tab->id('general')->label('General')->startPosition()
                    ->customField(function (CustomFieldBuilder $fieldBuilder) {
                        $fieldBuilder::text()
                            ->name('message')
                            ->label('Message')
                            ->validation('required|string|max:255');
                    })
                    ->customField(function (CustomFieldBuilder $fieldBuilder) {
                        $fieldBuilder::number()
                            ->name('retries')
                            ->label('Retries')
                            ->validation('nullable|integer|min:0|max:10');
                    });
            });
    }

    /**
     * @inheritDoc
     */
    protected function handle(FlowTaskContext $context): void
    {
        // Dummy implementation for testing
    }
}
