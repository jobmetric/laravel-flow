<?php

namespace JobMetric\Flow\Events\FlowState;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;
use JobMetric\Flow\Models\FlowState;

readonly class FlowStateDeleteEvent implements DomainEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public FlowState $flowState
    ) {
    }

    /**
     * Returns the stable technical key for the domain event.
     *
     * @return string
     */
    public static function key(): string
    {
        return 'flow_state.deleted';
    }

    /**
     * Returns the full metadata definition for this domain event.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'flow::base.events.flow_state_deleted.group', 'flow::base.events.flow_state_deleted.title', 'flow::base.events.flow_state_deleted.description', 'fas fa-trash-alt', [
            'flow',
            'flow_state',
            'storage',
            'management',
        ]);
    }
}
