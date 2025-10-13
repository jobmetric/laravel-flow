<?php

namespace JobMetric\Flow\Tests\Feature;

use Illuminate\Validation\ValidationException;
use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Flow\Models\Flow as FlowModel;
use JobMetric\Flow\Models\FlowState as FlowStateModel;
use JobMetric\Flow\Models\FlowTransition;
use JobMetric\Flow\Services\Flow as FlowService;
use JobMetric\Flow\Services\FlowState as FlowStateService;
use JobMetric\Flow\Tests\Stubs\Enums\OrderStatusEnum;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Flow\Tests\Stubs\Models\User;
use JobMetric\Flow\Tests\TestCase as BaseTestCase;
use Throwable;

class FlowStateServiceTest extends BaseTestCase
{
    /**
     * Build a valid flow payload for tests.
     *
     * @param string $subject
     * @param string|null $scope
     * @param int $version
     * @param bool $isDefault
     * @param bool $status
     * @param string|null $title
     * @return array<string,mixed>
     */
    protected function makeFlowPayload(string $subject, ?string $scope, int $version = 1, bool $isDefault = true, bool $status = true, ?string $title = null): array
    {
        $translation = [
            'en' => [
                'name' => $title ?? "Test Flow v{$version}",
                'description' => "Desc v{$version}",
            ],
        ];

        return [
            'subject_type' => $subject,
            'subject_scope' => $scope,
            'subject_collection' => null,
            'version' => $version,
            'is_default' => $isDefault,
            'status' => $status,
            'active_from' => null,
            'active_to' => null,
            'channel' => 'web',
            'ordering' => 0,
            'rollout_pct' => null,
            'environment' => 'test',
            'translation' => $translation,
        ];
    }

    /**
     * Store: creates a STATE node with normalized defaults and config merge.
     *
     * @throws Throwable
     */
    public function test_store_creates_state_with_defaults_and_merges_config(): void
    {
        $flowService = app(FlowService::class);
        $stateService = app(FlowStateService::class);

        $user = User::factory()->setName('Taraneh-Dad')->create();
        /** @var FlowModel $flow */
        $flow = FlowModel::query()->create(
            $this->makeFlowPayload(Order::class, (string)$user->id)
        );

        // Build a Middle state without explicit position; defaults should apply.
        $res = $stateService->store([
            'flow_id' => $flow->id,
            'translation' => [
                'en' => ['name' => 'Pending', 'description' => 'Middle node'],
            ],
            'status' => OrderStatusEnum::PENDING(), // valid per driver/status rule
            // 'position' intentionally omitted
            // 'color' omitted -> should fallback
            'is_terminal' => false,
        ]);

        $this->assertTrue($res->ok);
        /** @var FlowStateModel $state */
        $state = $res->data->resource;

        // type must be forced to STATE
        $this->assertEquals(
            FlowStateTypeEnum::STATE(),
            $state->type instanceof \UnitEnum ? $state->type->value : (string)$state->type
        );

        // Defaults merged in config
        $this->assertIsObject($state->config);
        $this->assertArrayHasKey('position', $state->config);
        $this->assertArrayHasKey('x', $state->config['position']);
        $this->assertArrayHasKey('y', $state->config['position']);
        $this->assertArrayHasKey('color', $state->config);
        $this->assertFalse((bool)($state->config['is_terminal'] ?? null));
    }

    /**
     * Store: position is optional, but if present it must contain numeric x,y.
     */
    public function test_store_position_optional_but_strict_when_present(): void
    {
        $stateService = app(FlowStateService::class);
        $flow = FlowModel::query()->create(
            $this->makeFlowPayload(Order::class, scope: '42')
        );

        // OK: omit position -> should pass
        $ok = $stateService->store([
            'flow_id' => $flow->id,
            'translation' => ['en' => ['name' => 'OK1', 'description' => null]],
            'status' => null,
        ]);
        $this->assertTrue($ok->ok);

        // Fail: present position with non-numeric values
        $this->expectException(ValidationException::class);
        $stateService->store([
            'flow_id' => $flow->id,
            'translation' => ['en' => ['name' => 'BadPos', 'description' => null]],
            'position' => ['x' => 'A', 'y' => 'B'],
        ]);
    }

    /**
     * Store: terminal state should carry end defaults (via normalize) and set config.is_terminal=true.
     *
     * @throws Throwable
     */
    public function test_store_terminal_sets_is_terminal_and_respects_end_defaults(): void
    {
        $stateService = app(FlowStateService::class);
        $flow = FlowModel::query()->create(
            $this->makeFlowPayload(Order::class, scope: '7')
        );

        $res = $stateService->store([
            'flow_id' => $flow->id,
            'translation' => ['en' => ['name' => 'Done', 'description' => 'Terminal']],
            'status' => 'pending',
            'is_terminal' => true,
            // provide a custom color to ensure it survives merges
            'color' => '#111111',
        ]);
        $this->assertTrue($res->ok);

        /** @var FlowStateModel $state */
        $state = $res->data->resource;
        $this->assertTrue((bool)($state->config['is_terminal'] ?? false));
        $this->assertEquals('#111111', $state->config['color'] ?? null);
    }

    /**
     * Store: duplicate translated "name" in same flow should fail uniqueness rule.
     */
    public function test_store_duplicate_translation_name_in_same_flow_fails(): void
    {
        $stateService = app(FlowStateService::class);
        $flow = FlowModel::query()->create(
            $this->makeFlowPayload(Order::class, scope: '100')
        );

        $ok = $stateService->store([
            'flow_id' => $flow->id,
            'translation' => ['en' => ['name' => 'UniqueName', 'description' => null]],
            'status' => null,
        ]);
        $this->assertTrue($ok->ok);

        $this->expectException(ValidationException::class);
        $stateService->store([
            'flow_id' => $flow->id,
            'translation' => ['en' => ['name' => 'UniqueName', 'description' => 'dup']],
            'status' => null,
        ]);
    }

    /**
     * Store: invalid domain status should be rejected by CheckStatusInDriverRule.
     */
    public function test_store_invalid_status_rejected(): void
    {
        $stateService = app(FlowStateService::class);
        $flow = FlowModel::query()->create(
            $this->makeFlowPayload(Order::class, scope: 'x')
        );

        $this->expectException(ValidationException::class);
        $stateService->store([
            'flow_id' => $flow->id,
            'translation' => ['en' => ['name' => 'BadStatus', 'description' => null]],
            'status' => 'not-a-valid-status',
        ]);
    }

    /**
     * Update: can toggle is_terminal and merge config, preserving prior values.
     *
     * @throws Throwable
     */
    public function test_update_toggles_is_terminal_and_merges_config(): void
    {
        $stateService = app(FlowStateService::class);
        $flow = FlowModel::query()->create(
            $this->makeFlowPayload(Order::class, scope: '55')
        );

        /** @var FlowStateModel $state */
        $state = $stateService->store([
            'flow_id' => $flow->id,
            'translation' => ['en' => ['name' => 'Middle', 'description' => null]],
            'status' => 'pending',
            'color' => '#222222',
        ])->data->resource;

        $res = $stateService->update($state->id, [
            'is_terminal' => true,
            'config' => [
                'position' => ['x' => 123.5, 'y' => -40.25],
            ],
        ]);

        $this->assertTrue($res->ok);
        $state->refresh();

        $this->assertTrue((bool)($state->config['is_terminal'] ?? false));
        $this->assertEquals(123.5, $state->config['position']['x']);
        $this->assertEquals(-40.25, $state->config['position']['y']);
        $this->assertEquals('#222222', $state->config['color']);
    }

    /**
     * Update: changing name to an existing one (same flow/locale) should fail.
     */
    public function test_update_duplicate_translation_name_fails(): void
    {
        $stateService = app(FlowStateService::class);
        $flow = FlowModel::query()->create(
            $this->makeFlowPayload(Order::class, scope: '88')
        );

        /** @var FlowStateModel $a */
        $a = $stateService->store([
            'flow_id' => $flow->id,
            'translation' => ['en' => ['name' => 'A', 'description' => null]],
            'status' => 'pending',
        ])->data->resource;

        /** @var FlowStateModel $b */
        $b = $stateService->store([
            'flow_id' => $flow->id,
            'translation' => ['en' => ['name' => 'B', 'description' => null]],
            'status' => 'pending',
        ])->data->resource;

        $this->expectException(ValidationException::class);
        $stateService->update($b->id, [
            'translation' => ['en' => ['name' => 'A']],
        ]);
    }

    /**
     * Delete: cannot delete START state; service must throw ValidationException.
     */
    public function test_destroy_rejects_start_state(): void
    {
        $flowService = app(FlowService::class);
        $stateService = app(FlowStateService::class);

        $user = User::factory()->create();

        // Create flow via service to auto-create START
        $created = $flowService->store(
            $this->makeFlowPayload(Order::class, (string)$user->id)
        );
        $this->assertTrue($created->ok);
        $flowId = $created->data->id;

        $start = $flowService->getStartState($flowId);
        $this->assertNotNull($start);
        $this->assertTrue($start->is_start);

        $this->expectException(ValidationException::class);
        $stateService->doDestroy($start->id);
    }

    /**
     * Scopes: start() and end() should behave as expected.
     */
    public function test_scopes_start_and_end(): void
    {
        $flow = FlowModel::query()->create(
            $this->makeFlowPayload(Order::class, scope: 'scopes')
        );

        // Manually add START (when creating flow directly, not via service)
        $start = $flow->states()->create([
            'translation' => ['en' => ['name' => 'Start', 'description' => null]],
            'type' => FlowStateTypeEnum::START(),
            'config' => ['is_terminal' => false],
            'status' => 'start',
        ]);

        $end = $flow->states()->create([
            'translation' => ['en' => ['name' => 'Done', 'description' => null]],
            'type' => FlowStateTypeEnum::STATE(),
            'config' => ['is_terminal' => true],
            'status' => 'done',
        ]);

        $startOnly = FlowStateModel::start()->where('flow_id', $flow->id)->get();
        $endOnly = FlowStateModel::end()->where('flow_id', $flow->id)->get();

        $this->assertCount(1, $startOnly);
        $this->assertEquals($start->id, $startOnly->first()->id);

        $this->assertCount(1, $endOnly);
        $this->assertEquals($end->id, $endOnly->first()->id);
    }

    /**
     * Consistency: adding incoming to START must fail validation in FlowService.
     *
     * @throws Throwable
     */
    public function test_incoming_to_start_breaks_consistency(): void
    {
        $flowService = app(FlowService::class);
        $stateService = app(FlowStateService::class);

        $flow = FlowModel::query()->create(
            $this->makeFlowPayload(Order::class, scope: 'cx')
        );

        // Add START and a middle node
        $start = $flow->states()->create([
            'translation' => ['en' => ['name' => 'Start', 'description' => null]],
            'type' => FlowStateTypeEnum::START(),
            'config' => ['is_terminal' => false],
            'status' => 'start',
        ]);

        /** @var FlowStateModel $mid */
        $mid = $stateService->store([
            'flow_id' => $flow->id,
            'translation' => ['en' => ['name' => 'Mid', 'description' => null]],
            'status' => 'pending',
        ])->data->resource;

        // Create an illegal transition -> incoming to START
        FlowTransition::query()->create([
            'flow_id' => $flow->id,
            'from' => $mid->id,
            'to' => $start->id,
            'slug' => 'illegal',
        ]);

        $this->expectException(ValidationException::class);
        $flowService->validateConsistency($flow->id);
    }
}
