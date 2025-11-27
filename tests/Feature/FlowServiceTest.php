<?php

namespace JobMetric\Flow\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Flow\Models\Flow as FlowModel;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Flow\Models\FlowTransition;
use JobMetric\Flow\Services\Flow as FlowService;
use JobMetric\Flow\Tests\Stubs\Enums\OrderStatusEnum;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Flow\Tests\Stubs\Models\User;
use JobMetric\Flow\Tests\TestCase as BaseTestCase;
use JobMetric\Language\Models\Language;
use Throwable;

class FlowServiceTest extends BaseTestCase
{
    /**
     * Build a valid flow payload.
     *
     * @param string $subject
     * @param string|null $scope
     * @param int $version
     * @param bool $isDefault
     * @param bool $status
     * @param string|null $title
     *
     * @return array<string,mixed>
     */
    protected function makeFlowPayload(string $subject, ?string $scope, int $version = 1, bool $isDefault = true, bool $status = true, string $title = null): array
    {
        $locales = Language::query()->where('status', true)->pluck('locale')->all();
        if (empty($locales)) {
            $locales = ['en'];
        }

        $translation = [];
        foreach ($locales as $loc) {
            $translation[$loc] = [
                'name' => $title ?? "Test Flow v{$version}",
                'description' => "Description v{$version}",
            ];
        }

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
            'title' => $title ?? 'Test Flow v' . $version,
            'translation' => $translation,
        ];
    }

    /**
     * @throws Throwable
     */
    public function test_store_creates_flow_and_start_state(): void
    {
        $service = app(FlowService::class);
        $user = User::factory()->setName('Majid')->create();

        $res = $service->store($this->makeFlowPayload(Order::class, (string)$user->id));

        $this->assertTrue($res->ok);
        $this->assertEquals(201, $res->status);

        /** @var FlowModel $flow */
        $flow = $res->data->resource;

        $start = $service->getStartState($flow->id);
        $this->assertInstanceOf(FlowState::class, $start);
        $this->assertEquals(
            FlowStateTypeEnum::START(),
            $start->type instanceof \UnitEnum ? $start->type->value : (string)$start->type
        );
    }

    /**
     * @throws Throwable
     */
    public function test_toggle_status_inverts_boolean_status(): void
    {
        $service = app(FlowService::class);
        $user = User::factory()->create();

        $flow = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id, version: 1, isDefault: true, status: true));
        $this->assertTrue((bool)$flow->status);

        $res = $service->toggleStatus($flow->id);
        $this->assertTrue($res->ok);

        $flow->refresh();
        $this->assertFalse((bool)$flow->status);
    }

    /**
     * @throws Throwable
     */
    public function test_set_default_unsets_others_in_same_scope_and_version(): void
    {
        $service = app(FlowService::class);
        $user = User::factory()->create();

        $f1 = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id, version: 1, isDefault: true));
        $f2 = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id, version: 1, isDefault: false));

        $this->assertTrue((bool)$f1->is_default);
        $this->assertFalse((bool)$f2->is_default);

        $res = $service->setDefault($f2->id);
        $this->assertTrue($res->ok);

        $f1->refresh();
        $f2->refresh();

        $this->assertFalse((bool)$f1->is_default);
        $this->assertTrue((bool)$f2->is_default);
    }

    /**
     * Flows with different scopes should not affect each other when setting default.
     *
     * @throws Throwable
     */
    public function test_set_default_preserves_other_scopes(): void
    {
        $service = app(FlowService::class);
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $fa = app(FlowService::class)->store($this->makeFlowPayload(Order::class, (string)$u1->id, title: 'fa'));
        $fb = app(FlowService::class)->store($this->makeFlowPayload(Order::class, (string)$u2->id, title: 'fb'));

        $res = $service->setDefault($fa->data->id);
        $this->assertTrue($res->ok);

        $fa->data->refresh();
        $fb->data->refresh();

        $this->assertTrue((bool)$fa->data->is_default);
        $this->assertTrue((bool)$fb->data->is_default);
    }

    /**
     * Invalid range should throw via dto() validation.
     */
    public function test_set_active_window_invalid_range_throws(): void
    {
        $this->expectException(ValidationException::class);

        $service = app(FlowService::class);
        $user = User::factory()->create();

        $flow = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id));

        $from = Carbon::now('UTC')->addDay();
        $to = Carbon::now('UTC');

        $service->setActiveWindow($flow->id, $from, $to);
    }

    /**
     * @throws Throwable
     */
    public function test_set_active_window_valid_range_sets_values(): void
    {
        $service = app(FlowService::class);
        $user = User::factory()->create();

        $flow = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id));

        $from = Carbon::now('UTC')->subDay();
        $to = Carbon::now('UTC')->addDay();

        $res = $service->setActiveWindow($flow->id, $from, $to);
        $this->assertTrue($res->ok);

        $flow->refresh();
        $this->assertEquals($from->toDateTimeString(), optional($flow->active_from)?->toDateTimeString());
        $this->assertEquals($to->toDateTimeString(), optional($flow->active_to)?->toDateTimeString());
    }

    /**
     * Clearing active window by passing nulls.
     *
     * @throws Throwable
     */
    public function test_set_active_window_clear_dates(): void
    {
        $service = app(FlowService::class);
        $user = User::factory()->create();

        $flow = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id));

        $service->setActiveWindow($flow->id, Carbon::now('UTC')->subHour(), Carbon::now('UTC')->addHour());
        $flow->refresh();
        $this->assertNotNull($flow->active_from);
        $this->assertNotNull($flow->active_to);

        $res = $service->setActiveWindow($flow->id, null, null);
        $this->assertTrue($res->ok);

        $flow->refresh();
        $this->assertNull($flow->active_from);
        $this->assertNull($flow->active_to);
    }

    /**
     * @throws Throwable
     */
    public function test_set_rollout_validates_and_applies_and_reset(): void
    {
        $service = app(FlowService::class);
        $user = User::factory()->create();

        $flow = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id));

        $res = $service->setRollout($flow->id, 50);
        $this->assertTrue($res->ok);

        $flow->refresh();
        $this->assertEquals(50, $flow->rollout_pct);

        $res = $service->setRollout($flow->id, null);
        $this->assertTrue($res->ok);

        $flow->refresh();
        $this->assertNull($flow->rollout_pct);
    }

    /**
     * Invalid rollout percent should throw via dto() validation.
     */
    public function test_set_rollout_out_of_range_throws(): void
    {
        $this->expectException(ValidationException::class);

        $service = app(FlowService::class);
        $user = User::factory()->create();
        $flow = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id));

        $service->setRollout($flow->id, 101);
    }

    /**
     * @throws Throwable
     */
    public function test_reorder_updates_ordering_sequence(): void
    {
        $service = app(FlowService::class);
        $user = User::factory()->create();

        $f1 = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id, version: 1));
        $f2 = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id, version: 2));
        $f3 = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id, version: 3));

        $res = $service->reorder([$f3->id, $f1->id, $f2->id]);
        $this->assertTrue($res->ok);

        $this->assertDatabaseHas('flows', ['id' => $f3->id, 'ordering' => 1]);
        $this->assertDatabaseHas('flows', ['id' => $f1->id, 'ordering' => 2]);
        $this->assertDatabaseHas('flows', ['id' => $f2->id, 'ordering' => 3]);
    }

    /**
     * Duplicate with states (STATE, terminal) and transitions.
     *
     * @throws Throwable
     */
    public function test_duplicate_with_graph_copies_states_and_transitions(): void
    {
        $service = app(FlowService::class);
        $user = User::factory()->create();

        /** @var FlowModel $flow */
        $flow = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id, version: 1, isDefault: true, status: true));

        // Manual START because we did not call store()
        $start = $flow->states()->create([
            'translation' => ['en' => ['name' => 'Start', 'description' => 'Start']],
            'type' => FlowStateTypeEnum::START(),
            'config' => ['is_terminal' => false],
            'status' => 'start',
        ]);

        $terminal = $flow->states()->create([
            'translation' => ['en' => ['name' => 'Done', 'description' => 'Terminal']],
            'type' => FlowStateTypeEnum::STATE(),
            'config' => ['is_terminal' => true],
            'status' => 'done',
        ]);

        if (method_exists($flow, 'transitions')) {
            $flow->transitions()->create([
                'from' => $start?->id,
                'to' => $terminal->id,
                'slug' => 'finish',
            ]);
        }

        $res = $service->duplicate($flow->id);
        $this->assertTrue($res->ok);

        /** @var FlowModel $copy */
        $copy = $res->data->resource;
        $this->assertEquals(($flow->version ?? 1) + 1, $copy->version);
        $this->assertFalse((bool)$copy->status);
        $this->assertFalse((bool)$copy->is_default);

        $this->assertEquals($flow->states()->count(), $copy->states()->count());

        if (method_exists($flow, 'transitions')) {
            $this->assertEquals($flow->transitions()->count(), $copy->transitions()->count());
        }

        $copiedTerminal = $copy->states()->where('status', 'done')->first();
        $this->assertNotNull($copiedTerminal);
        $this->assertTrue((bool)($copiedTerminal->config['is_terminal'] ?? false));
    }

    /**
     * Duplicate without graph.
     *
     * @throws Throwable
     */
    public function test_duplicate_without_graph_only_clones_flow_row(): void
    {
        $service = app(FlowService::class);
        $user = User::factory()->create();

        /** @var FlowModel $flow */
        $flow = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id, version: 2, isDefault: true, status: true));

        $flow->states()->create([
            'translation' => ['en' => ['name' => 'Any', 'description' => 'State']],
            'type' => FlowStateTypeEnum::STATE(),
            'config' => ['is_terminal' => false],
            'status' => 'any',
        ]);

        $res = $service->duplicate($flow->id, withGraph: false);
        $this->assertTrue($res->ok);

        /** @var FlowModel $copy */
        $copy = $res->data->resource;
        $this->assertEquals(($flow->version ?? 1) + 1, $copy->version);
        $this->assertEquals(0, $copy->states()->count());
    }

    /**
     * Get states list, map by status, and detect terminal via config.
     *
     * @throws Throwable
     */
    public function test_states_helpers_start_list_map_and_terminal_detection(): void
    {
        $service = app(FlowService::class);
        $user = User::factory()->create();

        /** @var FlowModel $flow */
        $flow = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id));

        $flow->states()->create([
            'translation' => ['en' => ['name' => 'Start', 'description' => 'Start']],
            'type' => FlowStateTypeEnum::START(),
            'config' => ['is_terminal' => false],
            'status' => 'start',
        ]);

        $terminal = $flow->states()->create([
            'translation' => ['en' => ['name' => 'Done', 'description' => 'Terminal']],
            'type' => FlowStateTypeEnum::STATE(),
            'config' => ['is_terminal' => true],
            'status' => 'done',
        ]);

        $start = $service->getStartState($flow->id);
        $this->assertInstanceOf(FlowState::class, $start);

        $list = $service->getStates($flow->id);
        $this->assertGreaterThanOrEqual(2, $list->count());

        $map = $service->getStatesByStatusMap($flow->id);
        $this->assertArrayHasKey('done', $map);
        $this->assertEquals($terminal->id, $map['done']->id);

        $this->assertTrue((bool)($map['done']->config['is_terminal'] ?? false));
    }

    /**
     * Exactly one START and no incoming to START.
     *
     * @throws Throwable
     */
    public function test_validate_consistency_ok_and_with_incoming_to_start_fails(): void
    {
        $flowService = app(FlowService::class);
        $stateService = app(\JobMetric\Flow\Services\FlowState::class);

        $user = User::factory()->create();

        $store = $flowService->store($this->makeFlowPayload(Order::class, (string)$user->id));
        $this->assertTrue($store->ok);

        $flowId = $store->data->id;

        $start = $flowService->getStartState($flowId);
        $this->assertNotNull($start);

        $ok = $flowService->validateConsistency($flowId);
        $this->assertTrue($ok->ok);

        $middleRes = $stateService->store([
            'flow_id' => $flowId,
            'translation' => [
                'en' => ['name' => 'Middle', 'description' => 'Mid'],
            ],
            'is_terminal' => false,
            'status' => 'pending',
        ]);
        $this->assertTrue($middleRes->ok);

        $middleId = $middleRes->data->id;

        $flow = FlowModel::query()->findOrFail($flowId);

        if (method_exists($flow, 'transitions')) {
            $flow->transitions()->create([
                'from' => $middleId,
                'to' => $start->id,
                'slug' => 'illegal_to_start',
            ]);
        } else {
            FlowTransition::query()->create([
                'flow_id' => $flowId,
                'from' => $middleId,
                'to' => $start->id,
                'slug' => 'illegal_to_start',
            ]);
        }

        try {
            $flowService->validateConsistency($flowId);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('flow', $e->errors());
        }
    }

    /**
     * @throws Throwable
     */
    public function test_preview_pick_uses_model_builder_and_picks_matching_flow(): void
    {
        $service = app(FlowService::class);
        $user = User::factory()->create();

        $flow = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id, version: 1, isDefault: true, status: true));

        $order = Order::factory()
            ->setUserID($user->id)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $picked = $service->previewPick($order);
        $this->assertInstanceOf(FlowModel::class, $picked);
        $this->assertEquals($flow->id, $picked->id);
    }

    /**
     * Export/import round-trip with graph.
     *
     * @throws Throwable
     */
    public function test_export_import_roundtrip_with_graph(): void
    {
        $service = app(FlowService::class);
        $user = User::factory()->create();

        /** @var FlowModel $flow */
        $flow = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id, version: 3, isDefault: true, status: true));

        $start = $flow->states()->create([
            'translation' => ['en' => ['name' => 'Start', 'description' => 'Start']],
            'type' => FlowStateTypeEnum::START(),
            'config' => ['is_terminal' => false],
            'status' => 'start',
        ]);

        $state = $flow->states()->create([
            'translation' => ['en' => ['name' => 'Task', 'description' => 'Task']],
            'type' => FlowStateTypeEnum::STATE(),
            'config' => ['is_terminal' => false],
            'status' => 'task',
        ]);

        if (method_exists($flow, 'transitions')) {
            $flow->transitions()->create([
                'from' => $start?->id,
                'to' => $state->id,
                'slug' => 'start_to_task',
            ]);
        }

        $export = $service->export($flow->id, true);
        $this->assertArrayHasKey('flow', $export);
        $this->assertArrayHasKey('states', $export);
        $this->assertArrayHasKey('transitions', $export);

        $imported = $service->import($export, [
            'is_default' => false,
            'status' => false,
        ]);

        $this->assertInstanceOf(FlowModel::class, $imported);
        $this->assertFalse((bool)$imported->is_default);
        $this->assertFalse((bool)$imported->status);

        $this->assertEquals($flow->states()->count(), $imported->states()->count());
        if (method_exists($flow, 'transitions')) {
            $this->assertEquals($flow->transitions()->count(), $imported->transitions()->count());
        }
    }

    /**
     * Export without graph and import should not create states/transitions.
     *
     * @throws Throwable
     */
    public function test_export_without_graph_then_import_creates_bare_flow(): void
    {
        $service = app(FlowService::class);
        $user = User::factory()->create();

        /** @var FlowModel $flow */
        $flow = FlowModel::query()->create($this->makeFlowPayload(Order::class, (string)$user->id, version: 4, isDefault: false, status: false));

        $flow->states()->create([
            'translation' => ['en' => ['name' => 'A', 'description' => 'A']],
            'type' => FlowStateTypeEnum::STATE(),
            'config' => ['is_terminal' => false],
            'status' => 'a',
        ]);

        $export = $service->export($flow->id, false);
        $this->assertArrayHasKey('flow', $export);
        $this->assertArrayHasKey('states', $export);
        $this->assertArrayHasKey('transitions', $export);
        $this->assertEmpty($export['states']);
        $this->assertEmpty($export['transitions']);

        $imported = $service->import($export, ['status' => true]);
        $this->assertInstanceOf(FlowModel::class, $imported);
        $this->assertTrue((bool)$imported->status);
        $this->assertEquals(0, $imported->states()->count());
    }
}
