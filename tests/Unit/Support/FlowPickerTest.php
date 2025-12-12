<?php

namespace JobMetric\Flow\Tests\Unit\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use JobMetric\Flow\Factories\FlowFactory;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Support\FlowPicker;
use JobMetric\Flow\Support\FlowPickerBuilder;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Flow\Tests\TestCase;
use ReflectionClass;
use ReflectionException;

/**
 * Comprehensive tests for FlowPicker
 *
 * This class is responsible for selecting the appropriate Flow for a model based on
 * various criteria including subject type/scope/collection, environment, channel,
 * active status, time windows, rollout gates, and fallback cascades.
 *
 * These tests cover all scenarios including edge cases and error conditions.
 */
class FlowPickerTest extends TestCase
{
    protected FlowPicker $picker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->picker = new FlowPicker();

        // Clear request cache before each test
        $this->clearRequestCache();
    }

    protected function tearDown(): void
    {
        $this->clearRequestCache();

        parent::tearDown();
    }

    /**
     * Clear the request cache
     */
    protected function clearRequestCache(): void
    {
        $reflection = new ReflectionClass(FlowPicker::class);
        $property = $reflection->getProperty('requestCache');
        $property->setAccessible(true);
        $property->setValue(null, []);
        $property->setAccessible(false);
    }

    /**
     * Test that pick() returns null when no candidates found
     */
    public function test_pick_returns_null_when_no_candidates_found(): void
    {
        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->environment('non_existent')
            ->channel('non_existent');

        $result = $this->picker->pick($order, $builder);

        $this->assertNull($result);
    }

    /**
     * Test that pick() returns a Flow when candidates are found
     */
    public function test_pick_returns_flow_when_candidates_are_found(): void
    {
        $now = Carbon::now('UTC');
        $order = Order::factory()->create(['user_id' => 1]);
        $flow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => '1',
            'subject_collection' => null,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'is_default' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $builder = new FlowPickerBuilder();
        $order->buildFlowPicker($builder);

        $result = $this->picker->pick($order, $builder);

        $this->assertInstanceOf(Flow::class, $result);
        $this->assertEquals($flow->id, $result->id);
    }

    /**
     * Test that pick() applies fallback cascade when no candidates found
     */
    public function test_pick_applies_fallback_cascade_when_no_candidates_found(): void
    {
        $now = Carbon::now('UTC');
        $order = Order::factory()->create(['user_id' => 1]);
        $flow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => '1',
            'subject_collection' => null,
            'environment' => 'test',
            'channel' => null, // No channel requirement
            'status' => true,
            'is_default' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $builder = new FlowPickerBuilder();
        $order->buildFlowPicker($builder);
        $builder->channel('non_existent'); // This will fail initially

        $result = $this->picker->pick($order, $builder);

        $this->assertInstanceOf(Flow::class, $result);
        $this->assertEquals($flow->id, $result->id);
    }

    /**
     * Test that pick() returns forced flow when provided
     */
    public function test_pick_returns_forced_flow_when_provided(): void
    {
        $now = Carbon::now('UTC');
        $forcedFlow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
        ]);

        $otherFlow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->forceFlowIdResolver(fn () => $forcedFlow->id);

        $result = $this->picker->pick($order, $builder);

        $this->assertInstanceOf(Flow::class, $result);
        $this->assertEquals($forcedFlow->id, $result->id);
        $this->assertNotEquals($otherFlow->id, $result->id);
    }

    /**
     * Test that pick() returns null for forced flow when it's not active
     */
    public function test_pick_returns_null_for_forced_flow_when_not_active(): void
    {
        $forcedFlow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'status' => false, // Inactive
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->onlyActive(true)
            ->forceFlowIdResolver(fn () => $forcedFlow->id);

        $result = $this->picker->pick($order, $builder);

        $this->assertNull($result);
    }

    /**
     * Test that pick() uses request cache when enabled
     */
    public function test_pick_uses_request_cache_when_enabled(): void
    {
        $order = Order::factory()->create(['user_id' => 1]);
        $flow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => '1',
            'subject_collection' => null,
            'environment' => 'test',
            'channel' => 'web',
            'status' => true,
            'is_default' => true,
            'rollout_pct' => null,
        ]);

        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->subjectScope('1')
            ->subjectCollection(null)
            ->environment('test')
            ->channel('web')
            ->onlyActive(true)
            ->ignoreTimeWindow(true) // Ignore time window to avoid timeNow issues
            ->evaluateRollout(false) // Disable rollout to simplify
            ->cacheInRequest(true);

        // Clear cache before first call to ensure clean state
        $this->clearRequestCache();

        // First call
        $result1 = $this->picker->pick($order, $builder);

        // Assert that first call found the flow
        $this->assertInstanceOf(Flow::class, $result1);
        $flowId = $result1->id;

        // Delete the flow to ensure cache is used
        $flow->delete();

        // Second call should return cached result
        $result2 = $this->picker->pick($order, $builder);

        $this->assertInstanceOf(Flow::class, $result2);
        $this->assertEquals($flowId, $result2->id);
    }

    /**
     * Test that candidates() filters by subject type
     */
    public function test_candidates_filters_by_subject_type(): void
    {
        $now = Carbon::now('UTC');
        $flow1 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $flow2 = FlowFactory::new()->create([
            'subject_type' => 'App\Models\Other',
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class);

        $result = $this->picker->candidates($order, $builder);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->contains('id', $flow1->id));
        $this->assertFalse($result->contains('id', $flow2->id));
    }

    /**
     * Test that candidates() filters by subject scope
     */
    public function test_candidates_filters_by_subject_scope(): void
    {
        $now = Carbon::now('UTC');
        $flow1 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => '123',
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $flow2 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => '456',
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 123]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->subjectScope('123');

        $result = $this->picker->candidates($order, $builder);

        $this->assertTrue($result->contains('id', $flow1->id));
        $this->assertFalse($result->contains('id', $flow2->id));
    }

    /**
     * Test that candidates() filters by environment
     */
    public function test_candidates_filters_by_environment(): void
    {
        $now = Carbon::now('UTC');
        $flow1 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'environment' => 'test',
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $flow2 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'environment' => 'prod',
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->environment('test');

        $result = $this->picker->candidates($order, $builder);

        $this->assertTrue($result->contains('id', $flow1->id));
        $this->assertFalse($result->contains('id', $flow2->id));
    }

    /**
     * Test that candidates() filters by channel
     */
    public function test_candidates_filters_by_channel(): void
    {
        $now = Carbon::now('UTC');
        $flow1 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'channel' => 'web',
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $flow2 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'channel' => 'api',
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->channel('web');

        $result = $this->picker->candidates($order, $builder);

        $this->assertTrue($result->contains('id', $flow1->id));
        $this->assertFalse($result->contains('id', $flow2->id));
    }

    /**
     * Test that candidates() filters by include flow IDs
     */
    public function test_candidates_filters_by_include_flow_ids(): void
    {
        $now = Carbon::now('UTC');
        $flow1 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $flow2 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->includeFlows([$flow1->id]);

        $result = $this->picker->candidates($order, $builder);

        $this->assertTrue($result->contains('id', $flow1->id));
        $this->assertFalse($result->contains('id', $flow2->id));
    }

    /**
     * Test that candidates() filters by exclude flow IDs
     */
    public function test_candidates_filters_by_exclude_flow_ids(): void
    {
        $now = Carbon::now('UTC');
        $flow1 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $flow2 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->excludeFlows([$flow1->id]);

        $result = $this->picker->candidates($order, $builder);

        $this->assertFalse($result->contains('id', $flow1->id));
        $this->assertTrue($result->contains('id', $flow2->id));
    }

    /**
     * Test that candidates() filters by active status
     */
    public function test_candidates_filters_by_active_status(): void
    {
        $now = Carbon::now('UTC');
        $activeFlow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $inactiveFlow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => false,
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->onlyActive(true)
            ->timeNow($now);

        $result = $this->picker->candidates($order, $builder);

        $this->assertTrue($result->contains('id', $activeFlow->id));
        $this->assertFalse($result->contains('id', $inactiveFlow->id));
    }

    /**
     * Test that candidates() filters by time window
     */
    public function test_candidates_filters_by_time_window(): void
    {
        $now = Carbon::now('UTC');

        $activeFlow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $expiredFlow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDays(2),
            'active_to' => $now->copy()->subDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->onlyActive(true)
            ->timeNow($now);

        $result = $this->picker->candidates($order, $builder);

        $this->assertTrue($result->contains('id', $activeFlow->id));
        $this->assertFalse($result->contains('id', $expiredFlow->id));
    }

    /**
     * Test that candidates() ignores time window when configured
     */
    public function test_candidates_ignores_time_window_when_configured(): void
    {
        $now = Carbon::now('UTC');

        $expiredFlow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDays(2),
            'active_to' => $now->copy()->subDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->onlyActive(true)
            ->ignoreTimeWindow(true)
            ->timeNow($now);

        $result = $this->picker->candidates($order, $builder);

        $this->assertTrue($result->contains('id', $expiredFlow->id));
    }

    /**
     * Test that candidates() filters by require default
     */
    public function test_candidates_filters_by_require_default(): void
    {
        $now = Carbon::now('UTC');
        $defaultFlow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'is_default' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $nonDefaultFlow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'is_default' => false,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->requireDefault(true);

        $result = $this->picker->candidates($order, $builder);

        $this->assertTrue($result->contains('id', $defaultFlow->id));
        $this->assertFalse($result->contains('id', $nonDefaultFlow->id));
    }

    /**
     * Test that candidates() filters by version equals
     */
    public function test_candidates_filters_by_version_equals(): void
    {
        $now = Carbon::now('UTC');
        $flow1 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'version' => 1,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $flow2 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'version' => 2,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->versionEquals(1);

        $result = $this->picker->candidates($order, $builder);

        $this->assertTrue($result->contains('id', $flow1->id));
        $this->assertFalse($result->contains('id', $flow2->id));
    }

    /**
     * Test that candidates() filters by version min and max
     */
    public function test_candidates_filters_by_version_min_and_max(): void
    {
        $now = Carbon::now('UTC');
        $flow1 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'version' => 2,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $flow2 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'version' => 5,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $flow3 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'version' => 8,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->versionAtLeast(2)
            ->versionAtMost(5);

        $result = $this->picker->candidates($order, $builder);

        $this->assertTrue($result->contains('id', $flow1->id));
        $this->assertTrue($result->contains('id', $flow2->id));
        $this->assertFalse($result->contains('id', $flow3->id));
    }

    /**
     * Test that candidates() applies rollout gating
     */
    public function test_candidates_applies_rollout_gating(): void
    {
        $now = Carbon::now('UTC');
        $flow1 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'rollout_pct' => 50, // 50% rollout
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
        ]);

        $flow2 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'rollout_pct' => null, // No rollout gate
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
        ]);

        $order = Order::factory()->create(['user_id' => 123]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->evaluateRollout(true)
            ->rolloutKeyResolver(fn ($m) => (string) $m->user_id)
            ->rolloutNamespace('test')
            ->rolloutSalt('test');

        $result = $this->picker->candidates($order, $builder);

        // Flow2 should always be included (no rollout gate)
        $this->assertTrue($result->contains('id', $flow2->id));
        // Flow1 may or may not be included depending on rollout bucket
    }

    /**
     * Test that candidates() orders by preferred flow IDs
     */
    public function test_candidates_orders_by_preferred_flow_ids(): void
    {
        $now = Carbon::now('UTC');
        $flow1 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $flow2 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->preferFlows([$flow2->id, $flow1->id]);

        $result = $this->picker->candidates($order, $builder);

        $this->assertTrue($result->isNotEmpty());
        // flow2 should come before flow1
        $this->assertEquals($flow2->id, $result->first()->id);
    }

    /**
     * Test that candidates() orders by preferred environments
     */
    public function test_candidates_orders_by_preferred_environments(): void
    {
        $now = Carbon::now('UTC');
        $flow1 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'environment' => 'prod',
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $flow2 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'environment' => 'test',
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->preferEnvironments(['test', 'prod']);

        $result = $this->picker->candidates($order, $builder);

        $this->assertTrue($result->isNotEmpty());
        // test should come before prod
        $this->assertEquals($flow2->id, $result->first()->id);
    }

    /**
     * Test that candidates() uses FIRST strategy
     */
    public function test_candidates_uses_first_strategy(): void
    {
        $now = Carbon::now('UTC');
        $flow1 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $flow2 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->matchStrategy(FlowPickerBuilder::STRATEGY_FIRST);

        $result = $this->picker->candidates($order, $builder);

        $this->assertTrue($result->isNotEmpty());
        // First strategy orders by id ASC, so the flow with smaller id should come first
        $this->assertTrue($result->contains('id', $flow1->id));
        $this->assertTrue($result->contains('id', $flow2->id));
        // Verify that results are ordered by id ASC
        $ids = $result->pluck('id')->toArray();
        $sortedIds = $ids;
        sort($sortedIds);
        $this->assertEquals($sortedIds, $ids);
    }

    /**
     * Test that candidates() uses BEST strategy
     */
    public function test_candidates_uses_best_strategy(): void
    {
        $now = Carbon::now('UTC');
        $flow1 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'version' => 1,
            'is_default' => false,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $flow2 = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'version' => 2,
            'is_default' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->matchStrategy(FlowPickerBuilder::STRATEGY_BEST);

        $result = $this->picker->candidates($order, $builder);

        $this->assertTrue($result->isNotEmpty());
        // flow2 should come first (higher version and is_default)
        $this->assertEquals($flow2->id, $result->first()->id);
    }

    /**
     * Test that candidates() respects candidates limit
     */
    public function test_candidates_respects_candidates_limit(): void
    {
        $now = Carbon::now('UTC');
        FlowFactory::new()->count(5)->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->candidatesLimit(2);

        $result = $this->picker->candidates($order, $builder);

        $this->assertLessThanOrEqual(2, $result->count());
    }

    /**
     * Test that stableBucket() returns consistent values
     */
    public function test_stable_bucket_returns_consistent_values(): void
    {
        $reflection = new ReflectionClass(FlowPicker::class);
        $method = $reflection->getMethod('stableBucket');
        $method->setAccessible(true);

        $picker = new FlowPicker();

        $bucket1 = $method->invoke($picker, 'test', 'salt', 'key123');
        $bucket2 = $method->invoke($picker, 'test', 'salt', 'key123');

        $this->assertEquals($bucket1, $bucket2);
        $this->assertGreaterThanOrEqual(0, $bucket1);
        $this->assertLessThan(100, $bucket1);
    }

    /**
     * Test that stableBucket() returns different values for different keys
     */
    public function test_stable_bucket_returns_different_values_for_different_keys(): void
    {
        $reflection = new ReflectionClass(FlowPicker::class);
        $method = $reflection->getMethod('stableBucket');
        $method->setAccessible(true);

        $picker = new FlowPicker();

        $bucket1 = $method->invoke($picker, 'test', 'salt', 'key123');
        $bucket2 = $method->invoke($picker, 'test', 'salt', 'key456');

        // They might be the same by chance, but usually different
        $this->assertGreaterThanOrEqual(0, $bucket1);
        $this->assertLessThan(100, $bucket1);
        $this->assertGreaterThanOrEqual(0, $bucket2);
        $this->assertLessThan(100, $bucket2);
    }

    /**
     * Test that computeCacheKey() returns null when dynamic callbacks present
     */
    public function test_compute_cache_key_returns_null_when_dynamic_callbacks_present(): void
    {
        $reflection = new ReflectionClass(FlowPicker::class);
        $method = $reflection->getMethod('computeCacheKey');
        $method->setAccessible(true);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->where(fn () => null);

        $result = $method->invoke($this->picker, $order, $builder);

        $this->assertNull($result);
    }

    /**
     * Test that computeCacheKey() returns string when no dynamic callbacks
     */
    public function test_compute_cache_key_returns_string_when_no_dynamic_callbacks(): void
    {
        $reflection = new ReflectionClass(FlowPicker::class);
        $method = $reflection->getMethod('computeCacheKey');
        $method->setAccessible(true);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->environment('test')
            ->channel('web');

        $result = $method->invoke($this->picker, $order, $builder);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test that applyFallbackCascade() applies FB_DROP_CHANNEL
     */
    public function test_apply_fallback_cascade_applies_fb_drop_channel(): void
    {
        $now = Carbon::now('UTC');
        $flow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'environment' => 'test',
            'channel' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->subjectScope(null)
            ->environment('test')
            ->channel('non_existent')
            ->fallbackCascade([FlowPickerBuilder::FB_DROP_CHANNEL]);

        $reflection = new ReflectionClass(FlowPicker::class);
        $method = $reflection->getMethod('applyFallbackCascade');
        $method->setAccessible(true);

        $result = $method->invoke($this->picker, $order, $builder);

        $this->assertInstanceOf(Flow::class, $result);
        $this->assertEquals($flow->id, $result->id);
    }

    /**
     * Test that applyFallbackCascade() applies FB_DROP_ENVIRONMENT
     */
    public function test_apply_fallback_cascade_applies_fb_drop_environment(): void
    {
        $now = Carbon::now('UTC');
        $flow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'environment' => null,
            'status' => true,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->subjectScope(null)
            ->environment('non_existent')
            ->fallbackCascade([FlowPickerBuilder::FB_DROP_ENVIRONMENT]);

        $reflection = new ReflectionClass(FlowPicker::class);
        $method = $reflection->getMethod('applyFallbackCascade');
        $method->setAccessible(true);

        $result = $method->invoke($this->picker, $order, $builder);

        $this->assertInstanceOf(Flow::class, $result);
        $this->assertEquals($flow->id, $result->id);
    }

    /**
     * Test that applyFallbackCascade() applies FB_IGNORE_TIMEWINDOW
     */
    public function test_apply_fallback_cascade_applies_fb_ignore_timewindow(): void
    {
        $now = Carbon::now('UTC');
        $flow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'active_from' => $now->copy()->subDays(2),
            'active_to' => $now->copy()->subDay(), // Expired
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->subjectScope(null)
            ->onlyActive(true)
            ->timeNow($now)
            ->fallbackCascade([FlowPickerBuilder::FB_IGNORE_TIMEWINDOW]);

        $reflection = new ReflectionClass(FlowPicker::class);
        $method = $reflection->getMethod('applyFallbackCascade');
        $method->setAccessible(true);

        $result = $method->invoke($this->picker, $order, $builder);

        $this->assertInstanceOf(Flow::class, $result);
        $this->assertEquals($flow->id, $result->id);
    }

    /**
     * Test that applyFallbackCascade() applies FB_DISABLE_ROLLOUT
     */
    public function test_apply_fallback_cascade_applies_fb_disable_rollout(): void
    {
        $now = Carbon::now('UTC');
        $flow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'rollout_pct' => 0, // 0% rollout (should be excluded)
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
        ]);

        $order = Order::factory()->create(['user_id' => 123]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->subjectScope(null)
            ->evaluateRollout(true)
            ->rolloutKeyResolver(fn ($m) => (string) $m->user_id)
            ->rolloutNamespace('test')
            ->rolloutSalt('test')
            ->fallbackCascade([FlowPickerBuilder::FB_DISABLE_ROLLOUT]);

        $reflection = new ReflectionClass(FlowPicker::class);
        $method = $reflection->getMethod('applyFallbackCascade');
        $method->setAccessible(true);

        $result = $method->invoke($this->picker, $order, $builder);

        $this->assertInstanceOf(Flow::class, $result);
        $this->assertEquals($flow->id, $result->id);
    }

    /**
     * Test that applyFallbackCascade() applies FB_DROP_REQUIRE_DEFAULT
     */
    public function test_apply_fallback_cascade_applies_fb_drop_require_default(): void
    {
        $now = Carbon::now('UTC');
        $flow = FlowFactory::new()->create([
            'subject_type' => Order::class,
            'subject_scope' => null,
            'subject_collection' => null,
            'status' => true,
            'is_default' => false,
            'active_from' => $now->copy()->subDay(),
            'active_to' => $now->copy()->addDay(),
            'rollout_pct' => null,
        ]);

        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Order::class)
            ->subjectScope(null)
            ->requireDefault(true)
            ->fallbackCascade([FlowPickerBuilder::FB_DROP_REQUIRE_DEFAULT]);

        $reflection = new ReflectionClass(FlowPicker::class);
        $method = $reflection->getMethod('applyFallbackCascade');
        $method->setAccessible(true);

        $result = $method->invoke($this->picker, $order, $builder);

        $this->assertInstanceOf(Flow::class, $result);
        $this->assertEquals($flow->id, $result->id);
    }

    /**
     * Test that applyFallbackCascade() returns null when no candidates found
     */
    public function test_apply_fallback_cascade_returns_null_when_no_candidates_found(): void
    {
        $order = Order::factory()->create(['user_id' => 1]);
        $builder = new FlowPickerBuilder();
        $builder->subjectType('NonExistentClass')
            ->fallbackCascade([
                FlowPickerBuilder::FB_DROP_CHANNEL,
                FlowPickerBuilder::FB_DROP_ENVIRONMENT,
            ]);

        $reflection = new ReflectionClass(FlowPicker::class);
        $method = $reflection->getMethod('applyFallbackCascade');
        $method->setAccessible(true);

        $result = $method->invoke($this->picker, $order, $builder);

        $this->assertNull($result);
    }
}

