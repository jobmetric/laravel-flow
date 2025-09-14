<?php

namespace JobMetric\Flow\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Flow\Models\FlowTask;

/**
 * @extends Factory<FlowTask>
 */
class FlowTaskFactory extends Factory
{
    protected $model = FlowTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flow_transition_id' => null,
            'driver' => null,
            'config' => [],
            'ordering' => 0,
            'status' => $this->faker->boolean(90),
        ];
    }

    /**
     * set flow_transition_id
     *
     * @param int $transition_id
     *
     * @return static
     */
    public function setTransition(int $transition_id): static
    {
        return $this->state(fn(array $attributes) => [
            'flow_transition_id' => $transition_id,
        ]);
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
            'driver' => $driver,
        ]);
    }

    /**
     * set config (replace)
     *
     * @param array|null $config
     *
     * @return static
     */
    public function setConfig(?array $config): static
    {
        return $this->state(fn(array $attributes) => [
            'config' => $config,
        ]);
    }

    /**
     * merge a single config key
     *
     * @param string $key
     * @param mixed $value
     *
     * @return static
     */
    public function addConfig(string $key, mixed $value): static
    {
        return $this->state(function (array $attributes) use ($key, $value) {
            $cfg = $attributes['config'] ?? [];
            $cfg[$key] = $value;

            return ['config' => $cfg];
        });
    }

    /**
     * set ordering (manual)
     *
     * @param int|null $ordering
     *
     * @return static
     */
    public function setOrdering(?int $ordering): static
    {
        return $this->state(fn(array $attributes) => [
            'ordering' => $ordering,
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
}
