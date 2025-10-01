<?php

namespace JobMetric\Flow\Tests\Stubs\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Flow\Tests\Stubs\Enums\OrderStatusEnum;
use JobMetric\Flow\Tests\Stubs\Models\Order;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'status' => $this->faker->randomElement(OrderStatusEnum::values()),
        ];
    }

    /**
     * set user id
     *
     * @param int $userId
     *
     * @return static
     */
    public function setUserID(int $userId): static
    {
        return $this->state(fn(array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    /**
     * set status
     *
     * @param OrderStatusEnum|string $status
     *
     * @return static
     */
    public function setStatus(OrderStatusEnum|string $status): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => $status,
        ]);
    }
}
