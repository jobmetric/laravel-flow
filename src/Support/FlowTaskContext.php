<?php

namespace JobMetric\Flow\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use JobMetric\Flow\DTO\TransitionResult;

class FlowTaskContext
{
    /**
     * Holds the main model instance that the flow is operating on, such as an Order or Ticket entity.
     *
     * @var Model
     */
    protected Model $subject;

    /**
     * Holds the authenticated user instance that triggered the flow transition, if any.
     *
     * @var Authenticatable|null
     */
    protected ?Authenticatable $user;

    /**
     * Holds the result of the action execution, if applicable.
     *
     * @var TransitionResult
     */
    protected TransitionResult $result;

    /**
     * Carries arbitrary input payload coming from the form or API request.
     *
     * @var array<string, mixed>
     */
    protected array $payload;

    /**
     * Caches the loaded configuration for this task instance.
     *
     * @var array<string,mixed>|null
     */
    protected ?array $config = null;

    /**
     * Creates a new flow task context instance with all relevant runtime data.
     *
     * @param Model $subject
     * @param TransitionResult $result
     * @param array $payload
     * @param Authenticatable|null $user
     */
    public function __construct(
        Model $subject,
        TransitionResult $result,
        array $payload = [],
        ?Authenticatable $user = null
    ) {
        $this->subject = $subject;
        $this->result = $result;
        $this->payload = $payload;
        $this->user = $user;
    }

    /**
     * Returns the main subject model that the flow is operating on.
     *
     * @return Model
     */
    public function subject(): Model
    {
        return $this->subject;
    }

    /**
     * Returns the result of the action execution associated with this task.
     *
     * @return TransitionResult
     */
    public function result(): TransitionResult
    {
        return $this->result;
    }

    /**
     * Returns the input payload associated with this task execution.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * Replaces the in-memory configuration for this task instance.
     * Use this to inject configuration from outside without hitting the database.
     *
     * @param array<string,mixed> $config
     *
     * @return static
     */
    public function replaceConfig(array $config): static
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Returns the cached configuration for this task instance.
     * No database calls are performed; returns an empty array when not set.
     *
     * @return array<string,mixed>
     */
    public function config(): array
    {
        return $this->config ?? [];
    }

    /**
     * Returns the authenticated user instance that triggered the flow transition.
     *
     * @return Authenticatable|null
     */
    public function user(): ?Authenticatable
    {
        return $this->user;
    }
}
