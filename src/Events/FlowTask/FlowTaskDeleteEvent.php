<?php

namespace JobMetric\Flow\Events\FlowTask;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;
use JobMetric\Flow\Models\FlowTask;

readonly class FlowTaskDeleteEvent implements DomainEvent
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
        return 'flow_task.deleted';
    }

    /**
     * Returns the full metadata definition for this domain event.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'flow::base.events.flow_task_deleted.group', 'flow::base.events.flow_task_deleted.title', 'flow::base.events.flow_task_deleted.description', 'fas fa-trash-alt', [
            'flow',
            'flow_task',
            'storage',
            'management',
        ]);
    }
}
