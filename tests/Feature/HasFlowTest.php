<?php

namespace JobMetric\Flow\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use JobMetric\Flow\Models\Flow as FlowModel;
use JobMetric\Flow\Tests\Stubs\Enums\OrderStatusEnum;
use JobMetric\Flow\Tests\Stubs\Models\SampleSimple;
use JobMetric\Flow\Tests\Stubs\Models\SimpleOrder;
use JobMetric\Flow\Tests\Stubs\Models\User;
use JobMetric\Flow\Tests\TestCase as BaseTestCase;
use LogicException;

class HasFlowTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        Config::set('test.flow_id', null);
        parent::tearDown();
    }

    public function test_auto_bind_on_create_when_flow_id_provided_via_attribute(): void
    {
        $user = User::factory()->create();

        $flow = FlowModel::query()->create([
            'subject_type'       => SimpleOrder::class,
            'subject_scope'      => null,
            'subject_collection' => null,
            'environment'        => null,
            'channel'            => null,
            'status'             => true,
            'active_from'        => null,
            'active_to'          => null,
            'is_default'         => false,
            'rollout_pct'        => null,
            'version'            => 1,
            'ordering'           => 0,
            'translation'        => [
                'fa' => ['name' => 'Simple Flow'],
            ],
        ]);

        $order = new SimpleOrder([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);
        $order->setFlowId($flow->id);
        $order->save();

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
            'flow_id'       => $flow->id,
        ]);

        $this->assertNotNull($order->boundFlow());
        $this->assertEquals($flow->id, $order->boundFlow()->id);
    }

    public function test_auto_bind_on_create_when_flow_id_provided_via_config(): void
    {
        $user = User::factory()->create();

        $flow = FlowModel::query()->create([
            'subject_type'       => SimpleOrder::class,
            'subject_scope'      => null,
            'subject_collection' => null,
            'environment'        => null,
            'channel'            => null,
            'status'             => true,
            'active_from'        => null,
            'active_to'          => null,
            'is_default'         => false,
            'rollout_pct'        => null,
            'version'            => 1,
            'ordering'           => 0,
            'translation'        => [
                'fa' => ['name' => 'Config Flow'],
            ],
        ]);

        Config::set('test.flow_id', $flow->id);

        $order = SimpleOrder::query()->create([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
            'flow_id'       => $flow->id,
        ]);

        $this->assertNotNull($order->boundFlow());
        $this->assertEquals($flow->id, $order->boundFlow()->id);
    }

    public function test_no_binding_when_flow_id_not_found(): void
    {
        $user = User::factory()->create();

        Config::set('test.flow_id', 99999); // Non-existent Flow ID

        $order = SimpleOrder::query()->create([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);

        $this->assertDatabaseMissing('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
        ]);

        $this->assertNull($order->boundFlow());
    }

    public function test_no_binding_when_no_flow_id_provided(): void
    {
        $user = User::factory()->create();

        $order = SimpleOrder::query()->create([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);

        $this->assertDatabaseMissing('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
        ]);

        $this->assertNull($order->boundFlow());
    }

    public function test_bind_flow_with_flow_instance(): void
    {
        $user = User::factory()->create();

        $flow = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Test Flow'],
            ],
        ]);

        $order = SimpleOrder::query()->create([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);

        $order->bindFlow($flow);

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
            'flow_id'       => $flow->id,
        ]);

        $this->assertEquals($flow->id, $order->boundFlow()->id);
    }

    public function test_bind_flow_with_flow_id(): void
    {
        $user = User::factory()->create();

        $flow = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Test Flow'],
            ],
        ]);

        $order = SimpleOrder::query()->create([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);

        $order->bindFlow($flow->id);

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
            'flow_id'       => $flow->id,
        ]);

        $this->assertEquals($flow->id, $order->boundFlow()->id);
    }

    public function test_bind_flow_with_invalid_flow_id_throws_exception(): void
    {
        $user = User::factory()->create();

        $order = SimpleOrder::query()->create([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Flow with ID 99999 not found.');

        $order->bindFlow(99999);
    }

    public function test_bind_flow_updates_existing_when_relation_loaded(): void
    {
        $user = User::factory()->create();

        $f1 = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Flow 1'],
            ],
        ]);

        $f2 = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Flow 2'],
            ],
        ]);

        $order = new SimpleOrder([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);
        $order->setFlowId($f1->id);
        $order->save();

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
            'flow_id'       => $f1->id,
        ]);

        // Preload relation to take the "relationLoaded" branch
        $order->load('flowUse');
        $order->bindFlow($f2);

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
            'flow_id'       => $f2->id,
        ]);

        $this->assertDatabaseMissing('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
            'flow_id'       => $f1->id,
        ]);
    }

    public function test_bind_flow_updates_existing_when_relation_not_loaded(): void
    {
        $user = User::factory()->create();

        $f1 = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Flow 1'],
            ],
        ]);

        $f2 = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Flow 2'],
            ],
        ]);

        $order = new SimpleOrder([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);
        $order->setFlowId($f1->id);
        $order->save();

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
            'flow_id'       => $f1->id,
        ]);

        // Do NOT preload relation -> trait should fetch existing and update it
        $order->bindFlow($f2);

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
            'flow_id'       => $f2->id,
        ]);
    }

    public function test_bind_flow_with_custom_used_at_timestamp(): void
    {
        $user = User::factory()->create();

        $flow = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Test Flow'],
            ],
        ]);

        $order = SimpleOrder::query()->create([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);

        $customTime = Carbon::now('UTC')->subDays(1);
        $order->bindFlow($flow, $customTime);

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
            'flow_id'       => $flow->id,
            'used_at'       => $customTime->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_unbind_flow_deletes_flow_use(): void
    {
        $user = User::factory()->create();

        $flow = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Test Flow'],
            ],
        ]);

        $order = new SimpleOrder([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);
        $order->setFlowId($flow->id);
        $order->save();

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
            'flow_id'       => $flow->id,
        ]);

        $order->unbindFlow();

        $this->assertDatabaseMissing('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
        ]);

        $this->assertNull($order->boundFlow());
    }

    public function test_scope_with_flow_eager_loads_relations(): void
    {
        $user = User::factory()->create();

        $flow = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Test Flow'],
            ],
        ]);

        $order = new SimpleOrder([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);
        $order->setFlowId($flow->id);
        $order->save();

        $loaded = SimpleOrder::query()->withFlow()->first();

        $this->assertTrue($loaded->relationLoaded('flowUse'));
        $this->assertTrue($loaded->flowUse->relationLoaded('flow'));
        $this->assertEquals($flow->id, $loaded->flowUse->flow->id);
    }

    public function test_status_enum_introspection_helpers(): void
    {
        $user = User::factory()->create();

        $flow = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Test Flow'],
            ],
        ]);

        $order = new SimpleOrder([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);
        $order->setFlowId($flow->id);
        $order->save();

        $this->assertEquals(OrderStatusEnum::class, $order->flowStatusEnumClass());

        $values = $order->flowStatusEnumValues();
        $this->assertIsArray($values);
        $this->assertContains('pending', $values);

        $current = $order->flowCurrentStatusValue();
        if ($current instanceof OrderStatusEnum) {
            $this->assertEquals('pending', $current->value);
        }
        else {
            $this->assertEquals('pending', $current);
        }
    }

    public function test_missing_status_column_throws_logic_exception(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must have a "status" column');

        // SampleSimple model doesn't have status column
        SampleSimple::query()->create();
    }


    public function test_bind_flow_with_invalid_type_throws_exception(): void
    {
        $user = User::factory()->create();

        $order = SimpleOrder::query()->create([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);

        // PHP 8+ will throw TypeError for type mismatch, not LogicException
        $this->expectException(\TypeError::class);

        // Pass invalid type (array instead of Flow|int)
        $order->bindFlow([]);
    }

    public function test_rebind_flow_works_with_has_workflow_method(): void
    {
        $user = User::factory()->create();

        $flow1 = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Flow 1'],
            ],
        ]);

        $flow2 = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Flow 2'],
            ],
        ]);

        $order = new SimpleOrder([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);
        $order->setFlowId($flow1->id);
        $order->save();

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
            'flow_id'       => $flow1->id,
        ]);

        // rebindFlow is inherited from HasWorkflow
        $picked = $order->rebindFlow();
        // Since we're using forceFlowIdResolver, it should still pick flow1
        $this->assertNotNull($picked);
        $this->assertEquals($flow1->id, $picked->id);
    }

    public function test_multiple_orders_can_bind_to_same_flow(): void
    {
        $user = User::factory()->create();

        $flow = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Shared Flow'],
            ],
        ]);

        $order1 = new SimpleOrder([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);
        $order1->setFlowId($flow->id);
        $order1->save();

        $order2 = new SimpleOrder([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);
        $order2->setFlowId($flow->id);
        $order2->save();

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order1->id,
            'flow_id'       => $flow->id,
        ]);

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order2->id,
            'flow_id'       => $flow->id,
        ]);

        $this->assertEquals($flow->id, $order1->boundFlow()->id);
        $this->assertEquals($flow->id, $order2->boundFlow()->id);
    }

    public function test_unbind_then_bind_again_works(): void
    {
        $user = User::factory()->create();

        $flow1 = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Flow 1'],
            ],
        ]);

        $flow2 = FlowModel::query()->create([
            'subject_type' => SimpleOrder::class,
            'status'       => true,
            'version'      => 1,
            'translation'  => [
                'fa' => ['name' => 'Flow 2'],
            ],
        ]);

        $order = new SimpleOrder([
            'user_id' => $user->id,
            'status'  => OrderStatusEnum::PENDING(),
        ]);
        $order->setFlowId($flow1->id);
        $order->save();

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
            'flow_id'       => $flow1->id,
        ]);

        $order->unbindFlow();

        $this->assertDatabaseMissing('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
        ]);

        $order->bindFlow($flow2);

        $this->assertDatabaseHas('flow_uses', [
            'flowable_type' => SimpleOrder::class,
            'flowable_id'   => $order->id,
            'flow_id'       => $flow2->id,
        ]);
    }
}
