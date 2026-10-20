<?php

namespace JobMetric\Flow\Events\FlowBinding;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;

/**
 * Describe a committed FlowBinding detached outcome using allowlisted identifiers.
 */
readonly class FlowBindingDetachCommittedEvent implements DomainEvent
{
    /**
     * Capture identifiers without models, input values or credentials.
     *
     * @param string $ownerType
     * @param int|string $ownerId
     * @param int $flowId
     *
     * @return void
     */
    public function __construct(
        public string $ownerType,
        public int|string $ownerId,
        public int $flowId
    ) {
    }

    /**
     * Return the stable key used by automation consumers.
     *
     * @return string
     */
    public static function key(): string
    {
        return 'workflow.flow_binding.detached.committed';
    }

    /**
     * Describe the outcome for translated discovery interfaces.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'workflow::automation.flow_binding_detached.group', 'workflow::automation.flow_binding_detached.title', 'workflow::automation.flow_binding_detached.description', 'fas fa-bolt', ['automation', 'committed', 'workflow']);
    }
}
