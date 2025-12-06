<?php

namespace JobMetric\Flow\Events\FlowTask;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;
use JobMetric\Flow\Models\FlowTask;

readonly class FlowTaskStoreEvent implements DomainEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public FlowTask $flowTask
    )
    {
    }

    /**
     * Returns the stable technical key for the domain event.
     *
     * @return string
     */
    public static function key(): string
    {
        return 'flow_task.stored';
    }

    /**
     * Returns the full metadata definition for this domain event.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'flow::base.events.flow_task_stored.group', 'flow::base.events.flow_task_stored.title', 'flow::base.events.flow_task_stored.description', 'fas fa-save', [
            'flow',
            'flow_task',
            'storage',
            'management',
        ]);
    }
}
