<?php

namespace JobMetric\Flow\Factories;

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
        return [
            'driver' => $this->faker->word,
            'status' => $this->faker->boolean
        ];
    }

    /**
     * set driver
     *
     * @param string $driver
     *
     * @return static
     */
    public function setDriver(string $driver): static
    {
        return $this->state(fn(array $attributes) => [
            'driver' => $driver
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
