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
            'slug' => $this->faker->word,
            'role_id' => null
        ];
    }

    /**
     * set flow id
     *
     * @param int $flow_id
     *
     * @return static
     */
    public function setFlow(int $flow_id): static
    {
        return $this->state(fn(array $attributes) => [
            'flow_id' => $flow_id
        ]);
    }

    /**
     * set from
     *
     * @param string $from
     *
     * @return static
     */
    public function setFrom(string $from): static
    {
        return $this->state(fn(array $attributes) => [
            'from' => $from
        ]);
    }

    /**
     * set to
     *
     * @param string $to
     *
     * @return static
     */
    public function setTo(string $to): static
    {
        return $this->state(fn(array $attributes) => [
            'to' => $to
        ]);
    }

    /**
     * set slug
     *
     * @param string $slug
     *
     * @return static
     */
    public function setSlug(string $slug): static
    {
        return $this->state(fn(array $attributes) => [
            'slug' => $slug
        ]);
    }

    /**
     * set role id
     *
     * @param int $role_id
     *
     * @return static
     */
    public function setRole(int $role_id): static
    {
        return $this->state(fn(array $attributes) => [
            'role_id' => $role_id
        ]);
    }
}
