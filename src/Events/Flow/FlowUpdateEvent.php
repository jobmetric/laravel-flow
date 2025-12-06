<?php

namespace JobMetric\Flow\Events\Flow;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;
use JobMetric\Flow\Models\Flow;

readonly class FlowUpdateEvent implements DomainEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public Flow  $flow,
        public array $data
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
        return 'flow.updated';
    }

    /**
     * Returns the full metadata definition for this domain event.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'flow::base.events.flow_updated.group', 'flow::base.events.flow_updated.title', 'flow::base.events.flow_updated.description', 'fas fa-edit', [
            'flow',
            'storage',
            'management',
        ]);
    }
}
