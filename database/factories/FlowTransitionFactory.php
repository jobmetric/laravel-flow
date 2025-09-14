<?php

namespace JobMetric\Flow\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Flow\Models\FlowTransition;

/**
 * @extends Factory<FlowTransition>
 */
class FlowTransitionFactory extends Factory
{
    protected $model = FlowTransition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flow_id' => null,
            'from' => null,
            'to' => null,
            'slug' => $this->faker->boolean(20) ? $this->randomSlug() : null,
        ];
    }

    /**
     * set flow_id
     *
     * @param int $flow_id
     *
     * @return static
     */
    public function setFlow(int $flow_id): static
    {
        return $this->state(fn(array $attributes) => [
            'flow_id' => $flow_id,
        ]);
    }

    /**
     * set from state id (nullable for start edge)
     *
     * @param int|null $from
     *
     * @return static
     */
    public function setFrom(?int $from): static
    {
        return $this->state(fn(array $attributes) => [
            'from' => $from,
        ]);
    }

    /**
     * set to state id (nullable for end edge)
     *
     * @param int|null $to
     *
     * @return static
     */
    public function setTo(?int $to): static
    {
        return $this->state(fn(array $attributes) => [
            'to' => $to,
        ]);
    }

    /**
     * define a normal edge between two states (from != to)
     *
     * @param int $fromId
     * @param int $toId
     *
     * @return static
     */
    public function between(int $fromId, int $toId): static
    {
        return $this->state(fn(array $attributes) => [
            'from' => $fromId,
            'to' => $toId,
        ]);
    }

    /**
     * define a START edge (from = null, to = $toStateId)
     *
     * @param int $toStateId
     *
     * @return static
     */
    public function startEdge(int $toStateId): static
    {
        return $this->state(fn(array $attributes) => [
            'from' => null,
            'to' => $toStateId,
        ]);
    }

    /**
     * define an END edge (from = $fromStateId, to = null)
     *
     * @param int $fromStateId
     *
     * @return static
     */
    public function endEdge(int $fromStateId): static
    {
        return $this->state(fn(array $attributes) => [
            'from' => $fromStateId,
            'to' => null,
        ]);
    }

    /**
     * set slug (leave null to auto-generate with randomSlug())
     *
     * @param string|null $slug
     *
     * @return static
     */
    public function setSlug(?string $slug): static
    {
        return $this->state(fn(array $attributes) => [
            'slug' => $slug,
        ]);
    }

    /**
     * set a random slug (lowercase [a-z0-9-])
     *
     * @return static
     */
    public function randomSlug(): static
    {
        return $this->state(fn(array $attributes) => [
            'slug' => str_replace('_', '-', $this->faker->slug()),
        ]);
    }

    /**
     * explicitly remove slug (allow multiple edges between same states)
     *
     * @return static
     */
    public function withoutSlug(): static
    {
        return $this->state(fn(array $attributes) => [
            'slug' => null,
        ]);
    }
}
