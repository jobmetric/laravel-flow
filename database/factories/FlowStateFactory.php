<?php

namespace JobMetric\Flow\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Flow\Models\FlowState;

/**
 * @extends Factory<FlowState>
 */
class FlowStateFactory extends Factory
{
    protected $model = FlowState::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flow_id' => null,
            'type' => $this->faker->word,
            'config' => $this->faker->boolean,
            'status' => $this->faker->word
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
     * set type
     *
     * @param string $type
     *
     * @return static
     */
    public function setType(string $type): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => $type
        ]);
    }

    /**
     * set config
     *
     * @param string $config
     *
     * @return static
     */
    public function setConfig(string $config): static
    {
        return $this->state(fn(array $attributes) => [
            'config' => $config
        ]);
    }

    /**
     * set status
     *
     * @param string $status
     *
     * @return static
     */
    public function setStatus(string $status): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => $status
        ]);
    }
}
