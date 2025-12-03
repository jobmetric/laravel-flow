<?php

namespace JobMetric\Flow\Tests\Stubs\Tasks;

use JobMetric\CustomField\CustomFieldBuilder;
use JobMetric\Flow\Contracts\AbstractRestrictionTask;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskDefinition;
use JobMetric\Flow\Support\RestrictionResult;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Form\FormBuilder;
use Throwable;

/**
 * Stub restriction task driver for testing FlowTransition runner.
 */
class DummyRestrictionTask extends AbstractRestrictionTask
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
        return new FlowTaskDefinition(title: 'Dummy Restriction Task', description: 'A dummy restriction task for testing purposes.',);
    }

    /**
     * @inheritDoc
     * @throws Throwable
     */
    public function form(): FormBuilder
    {
        return (new FormBuilder)->tab(function ($tab) {
                $tab->id('settings')->label('Settings')->startPosition()->customField(function (
                        CustomFieldBuilder $fieldBuilder
                    ) {
                        $fieldBuilder::checkbox()
                            ->name('allow_transition')
                            ->label('Allow Transition')
                            ->validation('required|boolean');
                    });
            });
    }

    /**
     * @inheritDoc
     */
    public function restriction(FlowTaskContext $context): RestrictionResult
    {
        $config = $context->config();
        $allowTransition = $config['allow_transition'] ?? true;

        if ($allowTransition) {
            return RestrictionResult::allow();
        }

        return RestrictionResult::deny('TRANSITION_NOT_ALLOWED', 'This transition is not allowed by the restriction task.');
    }
}
