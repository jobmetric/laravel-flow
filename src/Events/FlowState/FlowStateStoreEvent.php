<?php

namespace JobMetric\Flow\Events\FlowState;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;
use JobMetric\Flow\Models\FlowState;

readonly class FlowStateStoreEvent implements DomainEvent
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
        return 'flow_state.stored';
    }

    /**
     * Returns the full metadata definition for this domain event.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'flow::base.events.flow_state_stored.group', 'flow::base.events.flow_state_stored.title', 'flow::base.events.flow_state_stored.description', 'fas fa-save', [
            'flow',
            'flow_state',
            'storage',
            'management',
        ]);
    }
}
