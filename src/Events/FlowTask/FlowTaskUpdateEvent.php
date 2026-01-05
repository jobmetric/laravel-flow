<?php

namespace JobMetric\Flow\Events\FlowTask;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;
use JobMetric\Flow\Models\FlowTask;

readonly class FlowTaskUpdateEvent implements DomainEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public FlowTask $flowTask,
        public array $data
    ) {
    }

    /**
     * Returns the stable technical key for the domain event.
     *
     * @return string
     */
    public static function key(): string
    {
        return 'flow_task.updated';
    }

    /**
     * Returns the full metadata definition for this domain event.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'flow::base.events.flow_task_updated.group', 'flow::base.events.flow_task_updated.title', 'flow::base.events.flow_task_updated.description', 'fas fa-edit', [
            'flow',
            'flow_task',
            'storage',
            'management',
        ]);
    }
}
