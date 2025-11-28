<?php

namespace JobMetric\Flow\Events\FlowTransition;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;
use JobMetric\Flow\Models\FlowTransition;

readonly class FlowTransitionStoreEvent implements DomainEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public FlowTransition $flowTransition,
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
        return 'flow_transition.stored';
    }

    /**
     * Returns the full metadata definition for this domain event.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'flow::base.events.flow_transition_stored.group', 'flow::base.events.flow_transition_stored.title', 'flow::base.events.flow_transition_stored.description', 'fas fa-save', [
            'flow',
            'flow_transition',
            'storage',
            'management',
        ]);
    }
}
