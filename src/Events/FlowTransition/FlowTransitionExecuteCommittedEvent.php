<?php

namespace JobMetric\Flow\Events\FlowTransition;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;

/**
 * Describe a committed FlowTransition executed outcome using allowlisted identifiers.
 */
readonly class FlowTransitionExecuteCommittedEvent implements DomainEvent
{
    /**
     * Capture identifiers without models, input values or credentials.
     *
     * @param int|string $id
     * @param string $subjectType
     * @param int|string $subjectId
     *
     * @return void
     */
    public function __construct(
        public int|string $id,
        public string $subjectType,
        public int|string $subjectId
    ) {
    }

    /**
     * Return the stable key used by automation consumers.
     *
     * @return string
     */
    public static function key(): string
    {
        return 'workflow.flow_transition.executed.committed';
    }

    /**
     * Describe the outcome for translated discovery interfaces.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'workflow::automation.flow_transition_executed.group', 'workflow::automation.flow_transition_executed.title', 'workflow::automation.flow_transition_executed.description', 'fas fa-bolt', ['automation', 'committed', 'workflow']);
    }
}
