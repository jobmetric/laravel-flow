<?php

namespace JobMetric\Flow\Tests\Feature;

use Illuminate\Support\Carbon;
use JobMetric\Flow\Models\Flow as FlowModel;
use JobMetric\Flow\Tests\Stubs\Enums\OrderStatusEnum;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Flow\Tests\Stubs\Models\Sample;
use JobMetric\Flow\Tests\Stubs\Models\User;
use JobMetric\Flow\Tests\TestCase as BaseTestCase;
use LogicException;

class HasWorkflowTest extends BaseTestCase
{
    public function test_auto_bind_on_create_when_matching_flow_exists(): void
    {
        $user = User::factory()->setName('Majid')->create();

        // Create a matching active Flow
        $flow = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'subject_collection' => null,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'active_from' => null,
            'active_to' => null,
            'is_default' => true,
            'rollout_pct' => null,
            'version' => 1,
            'ordering' => 10,
            'title' => 'Order Flow v1',
        ]);

        $order = Order::factory()
            ->setUserID($user->id)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
            'flow_id' => $flow->id,
        ]);

        // boundFlow() convenience
        $this->assertNotNull($order->boundFlow());
        $this->assertEquals($flow->id, $order->boundFlow()->id);
    }

    public function test_no_binding_when_no_candidate_flow_matches(): void
    {
        $user = User::factory()->create();

        // Non-matching Flow (different environment and subject_scope)
        FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => '9999',
            'subject_collection' => null,
            'environment' => 'prod',
            'channel' => 'web',
            'status' => true,
            'active_from' => null,
            'active_to' => null,
            'is_default' => true,
            'rollout_pct' => null,
            'version' => 1,
            'ordering' => 1,
            'title' => 'Prod Flow',
        ]);

        $order = Order::factory()
            ->setUserID($user->id)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $this->assertDatabaseMissing('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
        ]);

        $this->assertNull($order->boundFlow());
    }

    public function test_rebind_flow_switches_to_newer_matching_flow(): void
    {
        $user = User::factory()->create();

        $old = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'subject_collection' => null,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'active_from' => null,
            'active_to' => null,
            'is_default' => true,
            'rollout_pct' => null,
            'version' => 1,
            'ordering' => 1,
            'title' => 'Old',
        ]);

        $order = Order::factory()
            ->setUserID($user->id)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
            'flow_id' => $old->id,
        ]);

        $new = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'subject_collection' => null,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'active_from' => null,
            'active_to' => null,
            'is_default' => true,
            'rollout_pct' => null,
            'version' => 2,
            'ordering' => 100,
            'title' => 'New',
        ]);

        $picked = $order->rebindFlow();
        $this->assertNotNull($picked);
        $this->assertEquals($new->id, $picked->id);

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
            'flow_id' => $new->id,
        ]);
    }

    public function test_unbind_flow_deletes_flow_use(): void
    {
        $user = User::factory()->create();

        $flow = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'subject_collection' => null,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'active_from' => null,
            'active_to' => null,
            'is_default' => true,
            'rollout_pct' => null,
            'version' => 1,
            'ordering' => 1,
            'title' => 'F1',
        ]);

        $order = Order::factory()
            ->setUserID($user->id)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
            'flow_id' => $flow->id,
        ]);

        $order->unbindFlow();

        $this->assertDatabaseMissing('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
        ]);
        $this->assertNull($order->boundFlow());
    }

    public function test_scope_with_flow_eager_loads_relations(): void
    {
        $user = User::factory()->create();

        $flow = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'subject_collection' => null,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'active_from' => null,
            'active_to' => null,
            'is_default' => true,
            'rollout_pct' => null,
            'version' => 1,
            'ordering' => 1,
            'title' => 'F1',
        ]);

        Order::factory()
            ->setUserID($user->id)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $loaded = Order::query()->withFlow()->first();

        $this->assertTrue($loaded->relationLoaded('flowUse'));
        $this->assertTrue($loaded->flowUse->relationLoaded('flow'));
        $this->assertEquals($flow->id, $loaded->flowUse->flow->id);
    }

    public function test_status_enum_introspection_helpers(): void
    {
        $user = User::factory()->create();

        $flow = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'subject_collection' => null,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'active_from' => null,
            'active_to' => null,
            'is_default' => true,
            'rollout_pct' => null,
            'version' => 1,
            'ordering' => 1,
            'title' => 'F1',
        ]);

        $order = Order::factory()
            ->setUserID($user->id)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $this->assertEquals(OrderStatusEnum::class, $order->flowStatusEnumClass());

        $values = $order->flowStatusEnumValues();
        $this->assertIsArray($values);
        $this->assertContains('pending', $values);

        $current = $order->flowCurrentStatusValue();
        // Depending on Laravel Enum cast behavior, current may be enum instance or value.
        if ($current instanceof OrderStatusEnum) {
            $this->assertEquals('pending', $current->value);
        } else {
            $this->assertEquals('pending', $current);
        }
    }

    public function test_missing_status_column_throws_logic_exception(): void
    {
        $this->expectException(LogicException::class);

        Sample::query()->create(); // No "status" column => ensureHasStatusColumn() should throw
    }

    public function test_active_window_and_fallback_cascade_behaviors(): void
    {
        $user = User::factory()->create();

        // Flow outside active window (should not match on first pass)
        FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'subject_collection' => null,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'active_from' => Carbon::now('UTC')->addDay(),
            'active_to' => Carbon::now('UTC')->addDays(2),
            'is_default' => true,
            'rollout_pct' => null,
            'version' => 1,
            'ordering' => 1,
            'title' => 'Future',
        ]);

        // A matching but different channel; will be used after FB_DROP_CHANNEL step
        $relaxed = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'subject_collection' => null,
            'environment' => 'test',
            'channel' => null,
            'status' => true,
            'active_from' => null,
            'active_to' => null,
            'is_default' => true,
            'rollout_pct' => null,
            'version' => 2,
            'ordering' => 5,
            'title' => 'Relaxed',
        ]);

        $order = Order::factory()
            ->setUserID($user->id)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
            'flow_id' => $relaxed->id,
        ]);
    }

    public function test_bind_flow_updates_existing_when_relation_loaded(): void
    {
        $user = User::factory()->create();

        $f1 = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'is_default' => true,
            'version' => 1,
            'ordering' => 1,
            'title' => 'F1',
        ]);

        // Build order NOW so auto-binding picks F1 (only F1 exists yet)
        $order = Order::factory()->setUserID($user->id)->setStatus(OrderStatusEnum::PENDING())->create();

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
            'flow_id' => $f1->id,
        ]);

        // Create newer flow AFTER initial bind
        $f2 = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'is_default' => true,
            'version' => 2,
            'ordering' => 2,
            'title' => 'F2',
        ]);

        // Preload relation to take the "relationLoaded" branch
        $order->load('flowUse');
        $order->bindFlow($f2);

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
            'flow_id' => $f2->id,
        ]);
    }

    public function test_bind_flow_updates_existing_when_relation_not_loaded(): void
    {
        $user = User::factory()->create();

        $f1 = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'is_default' => true,
            'version' => 1,
            'ordering' => 1,
            'title' => 'F1',
        ]);

        // Build order NOW so auto-binding picks F1
        $order = Order::factory()->setUserID($user->id)->setStatus(OrderStatusEnum::PENDING())->create();

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
            'flow_id' => $f1->id,
        ]);

        // Create newer flow AFTER initial bind
        $f2 = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'is_default' => true,
            'version' => 2,
            'ordering' => 2,
            'title' => 'F2',
        ]);

        // Do NOT preload relation -> trait should fetch existing and update it
        $order->bindFlow($f2);

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
            'flow_id' => $f2->id,
        ]);
    }

    public function test_rebind_with_tuner_respects_require_default_and_channel(): void
    {
        $user = User::factory()->create();

        // Non-default flow (should be ignored when requireDefault=true)
        FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'is_default' => false,
            'version' => 1,
            'ordering' => 1,
            'title' => 'non-default',
        ]);

        $default = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'is_default' => true,
            'version' => 2,
            'ordering' => 10,
            'title' => 'default',
        ]);

        $order = Order::factory()->setUserID($user->id)->setStatus(OrderStatusEnum::PENDING())->create();

        $picked = $order->rebindFlow(function ($b) {
            $b->requireDefault(true);
            $b->channel('web'); // ensure channel filter is applied
        });

        $this->assertNotNull($picked);
        $this->assertEquals($default->id, $picked->id);

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
            'flow_id' => $default->id,
        ]);
    }

    public function test_force_flow_id_resolver_honors_active_checks(): void
    {
        $user = User::factory()->create();

        $inactive = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'environment' => 'test',
            'channel' => 'web',
            'status' => false, // inactive
            'is_default' => true,
            'version' => 1,
            'ordering' => 1,
            'title' => 'inactive',
        ]);

        $active = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'is_default' => true,
            'version' => 2,
            'ordering' => 2,
            'title' => 'active',
        ]);

        $order = Order::factory()->setUserID($user->id)->setStatus(OrderStatusEnum::PENDING())->create();

        // Try to force inactive -> should NOT bind due to onlyActive(true) default
        $picked1 = $order->rebindFlow(function ($b) use ($inactive) {
            $b->forceFlowIdResolver(fn() => $inactive->id);
        });
        $this->assertNotNull($picked1); // fallback picked active/default one
        $this->assertEquals($active->id, $picked1->id);

        // Force active -> should bind to forced id
        $picked2 = $order->rebindFlow(function ($b) use ($active) {
            $b->forceFlowIdResolver(fn() => $active->id);
        });
        $this->assertNotNull($picked2);
        $this->assertEquals($active->id, $picked2->id);

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
            'flow_id' => $active->id,
        ]);
    }

    public function test_unbind_then_rebind_binds_again(): void
    {
        $user = User::factory()->create();

        $flow = FlowModel::query()->create([
            'subject_type' => Order::class,
            'subject_scope' => (string)$user->id,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'is_default' => true,
            'version' => 1,
            'ordering' => 1,
            'title' => 'F',
        ]);

        $order = Order::factory()->setUserID($user->id)->setStatus(OrderStatusEnum::PENDING())->create();

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
            'flow_id' => $flow->id,
        ]);

        $order->unbindFlow();
        $this->assertDatabaseMissing('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
        ]);

        $picked = $order->rebindFlow();
        $this->assertNotNull($picked);
        $this->assertEquals($flow->id, $picked->id);

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => Order::class,
            'flowable_id' => $order->id,
            'flow_id' => $flow->id,
        ]);
    }
}
