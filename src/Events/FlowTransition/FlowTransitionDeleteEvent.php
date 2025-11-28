<?php

namespace JobMetric\Flow\Events\FlowTransition;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;
use JobMetric\Flow\Models\FlowTransition;

readonly class FlowTransitionDeleteEvent implements DomainEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public FlowTransition $flow_transition
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
        return 'flow_transition.deleted';
    }

    /**
     * Returns the full metadata definition for this domain event.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'flow::base.events.flow_transition_deleted.group', 'flow::base.events.flow_transition_deleted.title', 'flow::base.events.flow_transition_deleted.description', 'fas fa-trash-alt', [
            'flow',
            'flow_transition',
            'storage',
            'management',
        ]);
    }
}
