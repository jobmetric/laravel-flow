<?php

namespace JobMetric\Flow\Events\FlowTask;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;

/**
 * Describe a committed FlowTask updated outcome using allowlisted identifiers.
 */
readonly class FlowTaskUpdateCommittedEvent implements DomainEvent
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
        return 'flow_task.updated.committed';
    }

    /**
     * Describe the outcome for translated discovery interfaces.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'workflow::automation.flow_task_updated.group', 'workflow::automation.flow_task_updated.title', 'workflow::automation.flow_task_updated.description', 'fas fa-bolt', ['automation', 'committed', 'workflow']);
    }
}
