<?php

namespace JobMetric\Flow\Factories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Flow\Models\FlowInstance;

/**
 * @extends Factory<FlowInstance>
 */
class FlowInstanceFactory extends Factory
{
    protected $model = FlowInstance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instanceable_type' => null,
            'instanceable_id' => null,

            'flow_transition_id' => null,

            'actor_type' => null,
            'actor_id' => null,

            'started_at' => $this->faker->dateTimeBetween('-10 days'),
            'completed_at' => null,
        ];
    }

    /**
     * set instanceable
     *
     * @param string $instanceable_type
     * @param int $instanceable_id
     *
     * @return static
     */
    public function setInstanceable(string $instanceable_type, int $instanceable_id): static
    {
        return $this->state(fn(array $attributes) => [
            'instanceable_type' => $instanceable_type,
            'instanceable_id' => $instanceable_id,
        ]);
    }

    /**
     * set flow_transition_id
     *
     * @param int $flow_transition_id
     *
     * @return static
     */
    public function setTransition(int $flow_transition_id): static
    {
        return $this->state(fn(array $attributes) => [
            'flow_transition_id' => $flow_transition_id,
        ]);
    }

    /**
     * set actor (nullable)
     *
     * @param string|null $actor_type
     * @param int|null $actor_id
     *
     * @return static
     */
    public function setActor(?string $actor_type, ?int $actor_id): static
    {
        return $this->state(fn(array $attributes) => [
            'actor_type' => $actor_type,
            'actor_id' => $actor_id,
        ]);
    }

    /**
     * set started_at
     *
     * @param DateTimeInterface|string $started_at
     *
     * @return static
     */
    public function setStartedAt(DateTimeInterface|string $started_at): static
    {
        return $this->state(fn(array $attributes) => [
            'started_at' => $started_at,
        ]);
    }

    /**
     * set completed_at (nullable)
     *
     * @param DateTimeInterface|string|null $completed_at
     *
     * @return static
     */
    public function setCompletedAt(DateTimeInterface|string|null $completed_at): static
    {
        return $this->state(fn(array $attributes) => [
            'completed_at' => $completed_at,
        ]);
    }
}
