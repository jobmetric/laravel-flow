<?php

namespace JobMetric\Flow\DTO;

/**
 * TransitionResult represents the outcome of running one or more action tasks
 * and carries both machine-readable and human-readable data through the pipeline.
 */
class TransitionResult
{
    /**
     * Flag indicating whether the overall pipeline or action set was successful.
     *
     * @var bool
     */
    protected bool $success = true;

    /**
     * Machine-readable status string (e.g. "ok", "failed").
     *
     * @var string
     */
    protected string $status = 'ok';

    /**
     * Collection of human-readable informational messages.
     *
     * @var array<int, string>
     */
    protected array $messages = [];

    /**
     * Collection of human-readable error messages.
     *
     * @var array<int, string>
     */
    protected array $errors = [];

    /**
     * Arbitrary data payload intended for consumers (e.g. controllers, API responses).
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Arbitrary metadata payload for debugging, tracing or internal usage.
     *
     * @var array<string, mixed>
     */
    protected array $meta = [];

    /**
     * Optional machine-readable code representing the result (e.g. for clients or logs).
     *
     * @var string|null
     */
    protected ?string $code = null;

    /**
     * Create a new TransitionResult instance.
     *
     * @param bool $success
     * @param string $status
     * @param string|null $code
     */
    public function __construct(bool $success = true, string $status = 'ok', ?string $code = null)
    {
        $this->success = $success;
        $this->status = $status;
        $this->code = $code;
    }

    /**
     * Create a successful TransitionResult with optional initial data.
     *
     * @param array<string, mixed> $data
     *
     * @return static
     */
    public static function success(array $data = []): self
    {
        $instance = new static(true, 'ok');
        $instance->data = $data;

        return $instance;
    }

    /**
     * Create a failed TransitionResult with an optional error message and code.
     *
     * @param string|null $message
     * @param string|null $code
     *
     * @return static
     */
    public static function failure(?string $message = null, ?string $code = null): self
    {
        $instance = new static(false, 'failed', $code);

        if ($message !== null) {
            $instance->errors[] = $message;
        }

        return $instance;
    }

    /**
     * Mark the result as successful and optionally update the status.
     *
     * @param string $status
     *
     * @return $this
     */
    public function markSuccess(string $status = 'ok'): self
    {
        $this->success = true;
        $this->status = $status;

        return $this;
    }

    /**
     * Mark the result as failed and optionally update the status and code.
     *
     * @param string $status
     * @param string|null $code
     *
     * @return $this
     */
    public function markFailed(string $status = 'failed', ?string $code = null): self
    {
        $this->success = false;
        $this->status = $status;
        $this->code = $code;

        return $this;
    }

    /**
     * Add an informational message to the result.
     *
     * @param string $message
     *
     * @return $this
     */
    public function addMessage(string $message): self
    {
        $this->messages[] = $message;

        return $this;
    }

    /**
     * Add an error message to the result and mark it as failed if required.
     *
     * @param string $error
     * @param bool $markFailed
     *
     * @return $this
     */
    public function addError(string $error, bool $markFailed = true): self
    {
        $this->errors[] = $error;

        if ($markFailed) {
            $this->success = false;
            $this->status = 'failed';
        }

        return $this;
    }

    /**
     * Merge an array of data into the existing data payload.
     *
     * @param array<string, mixed> $data
     *
     * @return $this
     */
    public function mergeData(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * Set a single data key on the result payload.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function setData(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Merge an array of metadata into the existing metadata payload.
     *
     * @param array<string, mixed> $meta
     *
     * @return $this
     */
    public function mergeMeta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Set a single metadata key on the result payload.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function setMeta(string $key, mixed $value): self
    {
        $this->meta[$key] = $value;

        return $this;
    }

    /**
     * Merge another TransitionResult into the current instance.
     * Messages, errors, data and meta are merged; success and status are combined.
     *
     * @param TransitionResult $other
     *
     * @return $this
     */
    public function merge(TransitionResult $other): self
    {
        $this->messages = array_merge($this->messages, $other->getMessages());
        $this->errors = array_merge($this->errors, $other->getErrors());
        $this->data = array_merge($this->data, $other->getData());
        $this->meta = array_merge($this->meta, $other->getMeta());

        if (! $other->isSuccess()) {
            $this->success = false;
            $this->status = $other->getStatus();
            $this->code = $other->getCode();
        }

        return $this;
    }

    /**
     * Determine whether the result is successful.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Determine whether the result contains any error messages.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Get the current status string.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Get the machine-readable result code, if any.
     *
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * Get all informational messages.
     *
     * @return array<int, string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get all error messages.
     *
     * @return array<int, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the data payload.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the metadata payload.
     *
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Convert the result into a plain array structure suitable for responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success'  => $this->success,
            'status'   => $this->status,
            'code'     => $this->code,
            'messages' => $this->messages,
            'errors'   => $this->errors,
            'data'     => $this->data,
            'meta'     => $this->meta,
        ];
    }
}
