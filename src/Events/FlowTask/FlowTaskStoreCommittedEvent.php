<?php

namespace JobMetric\Flow\Events\FlowTask;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;

/**
 * Describe a committed FlowTask created outcome using allowlisted identifiers.
 */
readonly class FlowTaskStoreCommittedEvent implements DomainEvent
{
    /**
     * Capture identifiers without models, input values or credentials.
     *
     * @param int|string $id
     *
     * @return void
     */
    public function __construct(
        public int|string $id
    ) {
    }

    /**
     * Return the stable key used by automation consumers.
     *
     * @return string
     */
    public static function key(): string
    {
        return 'flow_task.stored.committed';
    }

    /**
     * Describe the outcome for translated discovery interfaces.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'workflow::automation.flow_task_stored.group', 'workflow::automation.flow_task_stored.title', 'workflow::automation.flow_task_stored.description', 'fas fa-bolt', ['automation', 'committed', 'workflow']);
    }
}
