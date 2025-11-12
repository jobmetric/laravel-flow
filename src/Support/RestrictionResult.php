<?php

namespace JobMetric\Flow\Support;

class RestrictionResult
{
    /**
     * Indicates whether the requested operation is allowed to proceed.
     *
     * @var bool
     */
    protected bool $allowed;

    /**
     * Holds a machine-readable code that identifies the reason for the decision.
     *
     * @var string|null
     */
    protected ?string $code;

    /**
     * Holds a human-readable message that explains the decision for UI or logging.
     *
     * @var string|null
     */
    protected ?string $message;

    /**
     * Creates a new restriction result instance with the given attributes.
     *
     * @param bool $allowed                Indicates whether the restriction check has passed.
     * @param string|null $code            Provides a machine-readable code describing the decision.
     * @param string|null $message         Provides a human-readable explanation of the decision.
     */
    public function __construct(
        bool $allowed,
        ?string $code = null,
        ?string $message = null,
    ) {
        $this->allowed = $allowed;
        $this->code = $code;
        $this->message = $message;
    }

    /**
     * Builds a successful restriction result that allows the operation to proceed.
     *
     * @return static
     */
    public static function allow(): self
    {
        return new self(true);
    }

    /**
     * Builds a failed restriction result that denies the operation from proceeding.
     *
     * @param string $code                 Provides a machine-readable code describing why the operation is denied.
     * @param string|null $message         Provides a human-readable explanation of the denial.
     *
     * @return static
     */
    public static function deny(string $code, ?string $message = null): self
    {
        return new self(false, $code, $message);
    }

    /**
     * Determines whether the restriction check has passed and the operation is allowed.
     *
     * @return bool
     */
    public function allowed(): bool
    {
        return $this->allowed;
    }

    /**
     * Returns the machine-readable code that explains the decision for this result.
     *
     * @return string|null
     */
    public function code(): ?string
    {
        return $this->code;
    }

    /**
     * Returns the human-readable message that explains the decision for this result.
     *
     * @return string|null
     */
    public function message(): ?string
    {
        return $this->message;
    }
}
