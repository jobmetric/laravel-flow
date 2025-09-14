<?php

namespace JobMetric\Flow\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Flow\Enums\FlowStateTypeEnum;
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

            // default to a normal state to satisfy CHECK (status must be null)
            'type' => FlowStateTypeEnum::STATE(),
            'config' => [
                'color' => $this->faker->hexColor(),
                'position' => [
                    'x' => $this->faker->numberBetween(0, 1200),
                    'y' => $this->faker->numberBetween(0, 800),
                ],
            ],
            'status' => null,
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
     * set type
     *
     * @param FlowStateTypeEnum|string $type
     *
     * @return static
     */
    public function setType(FlowStateTypeEnum|string $type): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => $type,
        ]);
    }

    /**
     * set config (will replace entire config)
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
     * merge/override color inside config
     *
     * @param string $hex
     *
     * @return static
     */
    public function setColor(string $hex): static
    {
        return $this->state(function (array $attributes) use ($hex) {
            $cfg = $attributes['config'] ?? [];
            $cfg['color'] = $hex;

            return ['config' => $cfg];
        });
    }

    /**
     * merge/override position inside config
     *
     * @param int $x
     * @param int $y
     *
     * @return static
     */
    public function setPosition(int $x, int $y): static
    {
        return $this->state(function (array $attributes) use ($x, $y) {
            $cfg = $attributes['config'] ?? [];
            $cfg['position'] = ['x' => $x, 'y' => $y];

            return ['config' => $cfg];
        });
    }

    /**
     * set status (caller must ensure it matches domain rules)
     *
     * @param string|null $status
     *
     * @return static
     */
    public function setStatus(?string $status): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => $status,
        ]);
    }
}
