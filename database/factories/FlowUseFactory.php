<?php

namespace JobMetric\Flow\Factories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Flow\Models\FlowUse;

/**
 * @extends Factory<FlowUse>
 */
class FlowUseFactory extends Factory
{
    protected $model = FlowUse::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flow_id' => null,
            'flowable_type' => null,
            'flowable_id' => null,
            'used_at' => $this->faker->dateTimeBetween('-30 days'),
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
     * set flowable
     *
     * @param string $flowable_type
     * @param int $flowable_id
     *
     * @return static
     */
    public function setFlowable(string $flowable_type, int $flowable_id): static
    {
        return $this->state(fn(array $attributes) => [
            'flowable_type' => $flowable_type,
            'flowable_id' => $flowable_id,
        ]);
    }

    /**
     * set used_at
     *
     * @param DateTimeInterface|string $used_at
     *
     * @return static
     */
    public function setUsedAt(DateTimeInterface|string $used_at): static
    {
        return $this->state(fn(array $attributes) => [
            'used_at' => $used_at,
        ]);
    }
}
