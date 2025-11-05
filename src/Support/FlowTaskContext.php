<?php

namespace JobMetric\Flow\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
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
     * Carries arbitrary input payload coming from the form or API request.
     *
     * @var array<string, mixed>
     */
    public array $payload;

    /**
     * Holds the database identifier of the stored configuration record for this task instance.
     *
     * @var int
     */
    protected int $flowTaskId;

    /**
     * Creates a new flow task context instance with all relevant runtime data.
     *
     * @param Model $subject             The main model that the flow is currently working with.
     * @param int $flowTaskId            The ID of the stored configuration record for this task.
     * @param array $payload             The input payload associated with the transition.
     * @param Authenticatable|null $user The user that initiated the transition execution.
     */
    public function __construct(
        Model $subject,
        int $flowTaskId,
        array $payload = [],
        ?Authenticatable $user = null,
    ) {
        $this->subject = $subject;
        $this->flowTaskId = $flowTaskId;
        $this->payload = $payload;
        $this->user = $user;
    }

    /**
     * Returns the main subject model that the flow is operating on.
     *
     * @return Model|null
     */
    public function subject(): ?Model
    {
        return $this->subject;
    }

    /**
     * Returns the database identifier of the stored configuration record for this task instance.
     *
     * @return int
     */
    public function flowTask(): int
    {
        return $this->flowTaskId;
    }

    /**
     * Returns the input payload associated with this task execution.
     *
     * @return array
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
        /** @var FlowTask|null $record */
        $record = FlowTask::query()->find($this->flowTaskId);

        if ($record === null) {
            return [];
        }

        return $record->config ?? [];
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
