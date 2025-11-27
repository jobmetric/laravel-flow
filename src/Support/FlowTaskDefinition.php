<?php

namespace JobMetric\Flow\Support;

/**
 * Immutable definition object that holds metadata for a flow task,
 * including UI-oriented presentation details.
 */
readonly class FlowTaskDefinition
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $icon = null,
        public ?array $tags = null,
    ) {
    }
}
