<?php

namespace JobMetric\Flow\Contracts;

use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flyron\Facades\AsyncProcess;

abstract class AbstractActionTask extends AbstractTaskDriver
{
    /**
     * Determines whether this action should be executed asynchronously in a background process.
     *
     * @return bool
     */
    public function async(): bool
    {
        return false;
    }

    /**
     * Executes the task either synchronously or asynchronously depending on the async configuration.
     *
     * @param FlowTaskContext $context
     *
     * @return void
     */
    public function run(FlowTaskContext $context): void
    {
        if (! $this->async()) {
            $this->handle($context);

            return;
        }

        // Only use AsyncProcess if async is enabled
        $defaultOptions = [
            'label'   => static::class,
            'timeout' => null,
        ];

        $options = array_merge($defaultOptions, $this->backgroundOptions($context));

        AsyncProcess::run(function () use ($context): void {
            $this->handle($context);
        }, [], $options);
    }

    /**
     * Builds the options that control how this task is dispatched to a background process.
     * Override this method in concrete tasks to customize label, timeout or other supported flags.
     *
     * @param FlowTaskContext $context
     *
     * @return array<string,mixed>
     */
    protected function backgroundOptions(FlowTaskContext $context): array
    {
        return [];
    }

    /**
     * Executes the main action logic implemented by the concrete task.
     *
     * @param FlowTaskContext $context
     *
     * @return void
     */
    abstract protected function handle(FlowTaskContext $context): void;
}
