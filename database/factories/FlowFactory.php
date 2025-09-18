<?php

namespace JobMetric\Flow\Factories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Flow\Models\Flow;

/**
 * @extends Factory<Flow>
 */
class FlowFactory extends Factory
{
    protected $model = Flow::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // active window (ensure from <= to)
        $from = $this->faker->dateTimeBetween('-30 days', '+3 days');
        $to = $this->faker->dateTimeBetween($from, '+90 days');

        return [
            'subject_type' => null,
            'subject_scope' => null,

            'version' => 1,
            'is_default' => $this->faker->boolean(20),
            'status' => $this->faker->boolean(90),

            'active_from' => $from,
            'active_to' => $to,

            'channel' => $this->faker->randomElement(['web', 'api', 'pos', 'mobile/app']),
            'ordering' => $this->faker->numberBetween(0, 10),
            'rollout_pct' => $this->faker->numberBetween(0, 100),
            'environment' => $this->faker->randomElement(['dev', 'test', 'staging', 'prod']),
        ];
    }

    /**
     * set subject_type
     *
     * @param string $subject_type
     *
     * @return static
     */
    public function setSubjectType(string $subject_type): static
    {
        return $this->state(fn(array $attributes) => [
            'subject_type' => $subject_type,
        ]);
    }

    /**
     * set subject_scope
     *
     * @param string|null $subject_scope
     *
     * @return static
     */
    public function setSubjectScope(?string $subject_scope): static
    {
        return $this->state(fn(array $attributes) => [
            'subject_scope' => $subject_scope,
        ]);
    }

    /**
     * set version
     *
     * @param int $version
     *
     * @return static
     */
    public function setVersion(int $version): static
    {
        return $this->state(fn(array $attributes) => [
            'version' => $version,
        ]);
    }

    /**
     * set is_default
     *
     * @param bool $is_default
     *
     * @return static
     */
    public function setIsDefault(bool $is_default = true): static
    {
        return $this->state(fn(array $attributes) => [
            'is_default' => $is_default,
        ]);
    }

    /**
     * set status
     *
     * @param bool $status
     *
     * @return static
     */
    public function setStatus(bool $status): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * set active
     *
     * @param DateTimeInterface|string|null $from
     * @param DateTimeInterface|string|null $to
     *
     * @return static
     */
    public function setActive(DateTimeInterface|string|null $from, DateTimeInterface|string|null $to): static
    {
        return $this->state(fn(array $attributes) => [
            'active_from' => $from,
            'active_to' => $to,
        ]);
    }

    /**
     * set channel
     *
     * @param string|null $channel
     *
     * @return static
     */
    public function setChannel(?string $channel): static
    {
        return $this->state(fn(array $attributes) => [
            'channel' => $channel,
        ]);
    }

    /**
     * set ordering
     *
     * @param int $ordering
     *
     * @return static
     */
    public function setOrdering(int $ordering): static
    {
        return $this->state(fn(array $attributes) => [
            'ordering' => $ordering,
        ]);
    }

    /**
     * set rollout percentage
     *
     * @param int|null $rollout_pct 0..100 or null
     *
     * @return static
     */
    public function setRolloutPct(?int $rollout_pct): static
    {
        return $this->state(fn(array $attributes) => [
            'rollout_pct' => $rollout_pct,
        ]);
    }

    /**
     * set environment
     *
     * @param string|null $environment
     *
     * @return static
     */
    public function setEnvironment(?string $environment): static
    {
        return $this->state(fn(array $attributes) => [
            'environment' => $environment,
        ]);
    }
}
