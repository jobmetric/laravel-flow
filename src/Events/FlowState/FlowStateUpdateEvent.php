<?php

namespace JobMetric\Flow\Events\FlowState;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;
use JobMetric\Flow\Models\FlowState;

readonly class FlowStateUpdateEvent implements DomainEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public FlowState $flowState,
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
        return 'flow_state.updated';
    }

    /**
     * Returns the full metadata definition for this domain event.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'flow::base.events.flow_state_updated.group', 'flow::base.events.flow_state_updated.title', 'flow::base.events.flow_state_updated.description', 'fas fa-edit', [
            'flow',
            'flow_state',
            'storage',
            'management',
        ]);
    }
}
