<?php

namespace JobMetric\Flow\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use JobMetric\Flow\DTO\ActionResult;
use JobMetric\Flow\Models\FlowTask;

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
     * @var ActionResult
     */
    protected ActionResult $result;

    /**
     * Carries arbitrary input payload coming from the form or API request.
     *
     * @var array<string, mixed>
     */
    protected array $payload;

    /**
     * Holds the database identifier of the stored configuration record for this task instance.
     *
     * @var int
     */
    protected int $flowTaskId;

    /**
     * Caches the loaded configuration for this task instance.
     *
     * @var array<string,mixed>|null
     */
    protected ?array $config = null;

    /**
     * Creates a new flow task context instance with all relevant runtime data.
     *
     * @param Model $subject             The main model that the flow is currently working with.
     * @param int $flowTaskId            The ID of the stored configuration record for this task.
     * @param ActionResult $result       The result of the action execution, if applicable.
     * @param array $payload             The input payload associated with the transition.
     * @param Authenticatable|null $user The user that initiated the transition execution.
     */
    public function __construct(
        Model $subject,
        int $flowTaskId,
        ActionResult $result,
        array $payload = [],
        ?Authenticatable $user = null,
    ) {
        $this->subject = $subject;
        $this->flowTaskId = $flowTaskId;
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
     * Returns the database identifier of the stored configuration record for this task instance.
     *
     * @return int
     */
    public function flowTaskId(): int
    {
        return $this->flowTaskId;
    }

    /**
     * Returns the result of the action execution associated with this task.
     *
     * @return ActionResult
     */
    public function result(): ActionResult
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
     * Loads and returns the persisted configuration payload for this task instance.
     *
     * The configuration is expected to be stored in a FlowTask model with a "config" attribute
     * that is cast to an array. You may adjust the model class or attribute name to match your schema.
     *
     * @return array<string,mixed>
     */
    public function config(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        /** @var FlowTask|null $record */
        $record = FlowTask::query()->find($this->flowTaskId);

        $this->config = $record?->config ?? [];

        return $this->config;
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
