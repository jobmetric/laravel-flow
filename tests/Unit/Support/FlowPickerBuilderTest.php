<?php

namespace JobMetric\Flow\Tests\Unit\Support;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use JobMetric\Flow\Support\FlowPickerBuilder;
use JobMetric\Flow\Tests\TestCase;
use Mockery;

/**
 * Comprehensive tests for FlowPickerBuilder
 *
 * This class is responsible for configuring the constraints and selection strategy
 * used by FlowPicker to choose a Flow. It centralizes all filters (subject, environment,
 * channel, versions, rollout), ordering preferences, match strategy, fallback cascade
 * steps, and performance hints (per-request caching).
 *
 * These tests cover all methods, getters, setters, method chaining, default values,
 * validation, array normalization, constants, and edge cases.
 */
class FlowPickerBuilderTest extends TestCase
{
    /**
     * Test that subjectType() sets and returns the builder instance
     */
    public function test_subject_type_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->subjectType(Model::class);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals(Model::class, $builder->getSubjectType());
    }

    /**
     * Test that subjectType() can be set to null
     */
    public function test_subject_type_can_be_set_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->subjectType(Model::class);
        $this->assertEquals(Model::class, $builder->getSubjectType());

        $builder->subjectType('');
        $this->assertEquals('', $builder->getSubjectType());
    }

    /**
     * Test that subjectScope() sets and returns the builder instance
     */
    public function test_subject_scope_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->subjectScope('tenant123');

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals('tenant123', $builder->getSubjectScope());
    }

    /**
     * Test that subjectScope() can be set to null
     */
    public function test_subject_scope_can_be_set_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->subjectScope('tenant123');
        $this->assertEquals('tenant123', $builder->getSubjectScope());

        $builder->subjectScope(null);
        $this->assertNull($builder->getSubjectScope());
    }

    /**
     * Test that subjectCollection() sets and returns the builder instance
     */
    public function test_subject_collection_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->subjectCollection('epic');

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals('epic', $builder->getSubjectCollection());
    }

    /**
     * Test that subjectCollection() can be set to null
     */
    public function test_subject_collection_can_be_set_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->subjectCollection('epic');
        $this->assertEquals('epic', $builder->getSubjectCollection());

        $builder->subjectCollection(null);
        $this->assertNull($builder->getSubjectCollection());
    }

    /**
     * Test that environment() sets and returns the builder instance
     */
    public function test_environment_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->environment('prod');

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals('prod', $builder->getEnvironment());
    }

    /**
     * Test that environment() can be set to null
     */
    public function test_environment_can_be_set_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->environment('prod');
        $this->assertEquals('prod', $builder->getEnvironment());

        $builder->environment(null);
        $this->assertNull($builder->getEnvironment());
    }

    /**
     * Test that channel() sets and returns the builder instance
     */
    public function test_channel_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->channel('web');

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals('web', $builder->getChannel());
    }

    /**
     * Test that channel() can be set to null
     */
    public function test_channel_can_be_set_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->channel('web');
        $this->assertEquals('web', $builder->getChannel());

        $builder->channel(null);
        $this->assertNull($builder->getChannel());
    }

    /**
     * Test that onlyActive() sets and returns the builder instance
     */
    public function test_only_active_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->onlyActive(false);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertFalse($builder->isOnlyActive());
    }

    /**
     * Test that onlyActive() defaults to true
     */
    public function test_only_active_defaults_to_true(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertTrue($builder->isOnlyActive());
    }

    /**
     * Test that ignoreTimeWindow() sets and returns the builder instance
     */
    public function test_ignore_time_window_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->ignoreTimeWindow(true);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertTrue($builder->shouldIgnoreTimeWindow());
    }

    /**
     * Test that ignoreTimeWindow() defaults to false
     */
    public function test_ignore_time_window_defaults_to_false(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertFalse($builder->shouldIgnoreTimeWindow());
    }

    /**
     * Test that ignoreTimeWindow() can be called without arguments (defaults to true)
     */
    public function test_ignore_time_window_defaults_to_true_when_called_without_args(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->ignoreTimeWindow();
        $this->assertTrue($builder->shouldIgnoreTimeWindow());
    }

    /**
     * Test that timeNow() sets and returns the builder instance
     */
    public function test_time_now_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $now = Carbon::now('UTC');
        $result = $builder->timeNow($now);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertSame($now, $builder->getNowUtc());
    }

    /**
     * Test that timeNow() accepts DateTimeInterface
     *
     * @throws Exception
     */
    public function test_time_now_accepts_datetime_interface(): void
    {
        $builder = new FlowPickerBuilder();
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $builder->timeNow($now);

        $this->assertInstanceOf(DateTimeInterface::class, $builder->getNowUtc());
        $this->assertSame($now, $builder->getNowUtc());
    }

    /**
     * Test that timeNow() defaults to null
     */
    public function test_time_now_defaults_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertNull($builder->getNowUtc());
    }

    /**
     * Test that evaluateRollout() sets and returns the builder instance
     */
    public function test_evaluate_rollout_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->evaluateRollout(false);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertFalse($builder->shouldEvaluateRollout());
    }

    /**
     * Test that evaluateRollout() defaults to true
     */
    public function test_evaluate_rollout_defaults_to_true(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertTrue($builder->shouldEvaluateRollout());
    }

    /**
     * Test that rolloutNamespace() sets and returns the builder instance
     */
    public function test_rollout_namespace_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->rolloutNamespace('order');

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals('order', $builder->getRolloutNamespace());
    }

    /**
     * Test that rolloutNamespace() can be set to null
     */
    public function test_rollout_namespace_can_be_set_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->rolloutNamespace('order');
        $this->assertEquals('order', $builder->getRolloutNamespace());

        $builder->rolloutNamespace(null);
        $this->assertNull($builder->getRolloutNamespace());
    }

    /**
     * Test that rolloutSalt() sets and returns the builder instance
     */
    public function test_rollout_salt_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->rolloutSalt('tests');

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals('tests', $builder->getRolloutSalt());
    }

    /**
     * Test that rolloutSalt() can be set to null
     */
    public function test_rollout_salt_can_be_set_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->rolloutSalt('tests');
        $this->assertEquals('tests', $builder->getRolloutSalt());

        $builder->rolloutSalt(null);
        $this->assertNull($builder->getRolloutSalt());
    }

    /**
     * Test that rolloutKeyResolver() sets and returns the builder instance
     */
    public function test_rollout_key_resolver_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $resolver = fn (Model $m) => (string) $m->getKey();
        $result = $builder->rolloutKeyResolver($resolver);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertSame($resolver, $builder->getRolloutKeyResolver());
    }

    /**
     * Test that rolloutKeyResolver() can be set to null
     */
    public function test_rollout_key_resolver_defaults_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertNull($builder->getRolloutKeyResolver());
    }

    /**
     * Test that rolloutKeyResolver() can be called with a closure
     */
    public function test_rollout_key_resolver_accepts_closure(): void
    {
        $builder = new FlowPickerBuilder();
        $resolver = function (Model $model): ?string {
            return $model->getAttribute('user_id') ? (string) $model->getAttribute('user_id') : null;
        };
        $builder->rolloutKeyResolver($resolver);

        $this->assertIsCallable($builder->getRolloutKeyResolver());
    }

    /**
     * Test that where() adds a callback and returns the builder instance
     */
    public function test_where_adds_callback_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $callback = function (Builder $q, Model $m): void {
            $q->where('custom_field', 'value');
        };
        $result = $builder->where($callback);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $callbacks = $builder->getWhereCallbacks();
        $this->assertCount(1, $callbacks);
        $this->assertSame($callback, $callbacks[0]);
    }

    /**
     * Test that where() can be called multiple times to add multiple callbacks
     */
    public function test_where_can_be_called_multiple_times(): void
    {
        $builder = new FlowPickerBuilder();
        $callback1 = function (Builder $q, Model $m): void {
            $q->where('field1', 'value1');
        };
        $callback2 = function (Builder $q, Model $m): void {
            $q->where('field2', 'value2');
        };

        $builder->where($callback1)->where($callback2);

        $callbacks = $builder->getWhereCallbacks();
        $this->assertCount(2, $callbacks);
        $this->assertSame($callback1, $callbacks[0]);
        $this->assertSame($callback2, $callbacks[1]);
    }

    /**
     * Test that where() defaults to empty array
     */
    public function test_where_defaults_to_empty_array(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertIsArray($builder->getWhereCallbacks());
        $this->assertEmpty($builder->getWhereCallbacks());
    }

    /**
     * Test that orderByDefault() sets ordering callback and returns the builder instance
     */
    public function test_order_by_default_sets_callback_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->orderByDefault();

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertNotNull($builder->getOrderingCallback());
        $this->assertIsCallable($builder->getOrderingCallback());
    }

    /**
     * Test that orderByDefault() sets the correct ordering
     */
    public function test_order_by_default_sets_correct_ordering(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->orderByDefault();

        $callback = $builder->getOrderingCallback();
        $this->assertNotNull($callback);

        // Create a mock query builder to test the callback
        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('orderByDesc')->with('version')->once()->andReturnSelf();
        $mockQuery->shouldReceive('orderByDesc')->with('is_default')->once()->andReturnSelf();
        $mockQuery->shouldReceive('orderByDesc')->with('ordering')->once()->andReturnSelf();
        $mockQuery->shouldReceive('orderByDesc')->with('id')->once()->andReturnSelf();

        $callback($mockQuery);
    }

    /**
     * Test that orderBy() sets custom ordering callback and returns the builder instance
     */
    public function test_order_by_sets_custom_callback_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $callback = function (Builder $q): void {
            $q->orderBy('custom_field', 'asc');
        };
        $result = $builder->orderBy($callback);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertSame($callback, $builder->getOrderingCallback());
    }

    /**
     * Test that orderBy() can override orderByDefault()
     */
    public function test_order_by_can_override_order_by_default(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->orderByDefault();
        $defaultCallback = $builder->getOrderingCallback();

        $customCallback = function (Builder $q): void {
            $q->orderBy('custom', 'asc');
        };
        $builder->orderBy($customCallback);

        $this->assertSame($customCallback, $builder->getOrderingCallback());
        $this->assertNotSame($defaultCallback, $builder->getOrderingCallback());
    }

    /**
     * Test that orderBy() defaults to null
     */
    public function test_order_by_defaults_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertNull($builder->getOrderingCallback());
    }

    /**
     * Test that matchStrategy() sets and returns the builder instance
     */
    public function test_match_strategy_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->matchStrategy(FlowPickerBuilder::STRATEGY_FIRST);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals(FlowPickerBuilder::STRATEGY_FIRST, $builder->getMatchStrategy());
    }

    /**
     * Test that matchStrategy() defaults to STRATEGY_BEST
     */
    public function test_match_strategy_defaults_to_best(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertEquals(FlowPickerBuilder::STRATEGY_BEST, $builder->getMatchStrategy());
    }

    /**
     * Test that pickFirstMatch() sets strategy to FIRST and returns the builder instance
     */
    public function test_pick_first_match_sets_strategy_to_first(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->pickFirstMatch(true);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals(FlowPickerBuilder::STRATEGY_FIRST, $builder->getMatchStrategy());
    }

    /**
     * Test that pickFirstMatch(false) sets strategy to BEST
     */
    public function test_pick_first_match_false_sets_strategy_to_best(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->pickFirstMatch(true);
        $this->assertEquals(FlowPickerBuilder::STRATEGY_FIRST, $builder->getMatchStrategy());

        $builder->pickFirstMatch(false);
        $this->assertEquals(FlowPickerBuilder::STRATEGY_BEST, $builder->getMatchStrategy());
    }

    /**
     * Test that pickFirstMatch() defaults to true when called without arguments
     */
    public function test_pick_first_match_defaults_to_true_when_called_without_args(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->pickFirstMatch();
        $this->assertEquals(FlowPickerBuilder::STRATEGY_FIRST, $builder->getMatchStrategy());
    }

    /**
     * Test that requireDefault() sets and returns the builder instance
     */
    public function test_require_default_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->requireDefault(true);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertTrue($builder->isRequireDefault());
    }

    /**
     * Test that requireDefault() defaults to false
     */
    public function test_require_default_defaults_to_false(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertFalse($builder->isRequireDefault());
    }

    /**
     * Test that requireDefault() defaults to true when called without arguments
     */
    public function test_require_default_defaults_to_true_when_called_without_args(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->requireDefault();
        $this->assertTrue($builder->isRequireDefault());
    }

    /**
     * Test that includeFlows() sets and returns the builder instance
     */
    public function test_include_flows_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->includeFlows([1, 2, 3]);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals([1, 2, 3], $builder->getIncludeFlowIds());
    }

    /**
     * Test that includeFlows() normalizes and deduplicates IDs
     */
    public function test_include_flows_normalizes_and_deduplicates_ids(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->includeFlows(['1', '2', 2, 3, '3', 4]);

        $ids = $builder->getIncludeFlowIds();
        $this->assertEquals([1, 2, 3, 4], $ids);
    }

    /**
     * Test that includeFlows() handles empty array
     */
    public function test_include_flows_handles_empty_array(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->includeFlows([]);
        $this->assertEmpty($builder->getIncludeFlowIds());
    }

    /**
     * Test that includeFlows() defaults to empty array
     */
    public function test_include_flows_defaults_to_empty_array(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertIsArray($builder->getIncludeFlowIds());
        $this->assertEmpty($builder->getIncludeFlowIds());
    }

    /**
     * Test that excludeFlows() sets and returns the builder instance
     */
    public function test_exclude_flows_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->excludeFlows([1, 2, 3]);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals([1, 2, 3], $builder->getExcludeFlowIds());
    }

    /**
     * Test that excludeFlows() normalizes and deduplicates IDs
     */
    public function test_exclude_flows_normalizes_and_deduplicates_ids(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->excludeFlows(['1', '2', 2, 3, '3', 4]);

        $ids = $builder->getExcludeFlowIds();
        $this->assertEquals([1, 2, 3, 4], $ids);
    }

    /**
     * Test that excludeFlows() handles empty array
     */
    public function test_exclude_flows_handles_empty_array(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->excludeFlows([]);
        $this->assertEmpty($builder->getExcludeFlowIds());
    }

    /**
     * Test that excludeFlows() defaults to empty array
     */
    public function test_exclude_flows_defaults_to_empty_array(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertIsArray($builder->getExcludeFlowIds());
        $this->assertEmpty($builder->getExcludeFlowIds());
    }

    /**
     * Test that preferFlows() sets and returns the builder instance
     */
    public function test_prefer_flows_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->preferFlows([1, 2, 3]);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals([1, 2, 3], $builder->getPreferFlowIds());
    }

    /**
     * Test that preferFlows() normalizes and deduplicates IDs
     */
    public function test_prefer_flows_normalizes_and_deduplicates_ids(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->preferFlows(['1', '2', 2, 3, '3', 4]);

        $ids = $builder->getPreferFlowIds();
        $this->assertEquals([1, 2, 3, 4], $ids);
    }

    /**
     * Test that preferFlows() handles empty array
     */
    public function test_prefer_flows_handles_empty_array(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->preferFlows([]);
        $this->assertEmpty($builder->getPreferFlowIds());
    }

    /**
     * Test that preferFlows() defaults to empty array
     */
    public function test_prefer_flows_defaults_to_empty_array(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertIsArray($builder->getPreferFlowIds());
        $this->assertEmpty($builder->getPreferFlowIds());
    }

    /**
     * Test that versionEquals() sets and returns the builder instance
     */
    public function test_version_equals_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->versionEquals(5);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals(5, $builder->getVersionEquals());
    }

    /**
     * Test that versionEquals() defaults to null
     */
    public function test_version_equals_defaults_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertNull($builder->getVersionEquals());
    }

    /**
     * Test that versionAtLeast() sets and returns the builder instance
     */
    public function test_version_at_least_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->versionAtLeast(3);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals(3, $builder->getVersionMin());
    }

    /**
     * Test that versionAtLeast() defaults to null
     */
    public function test_version_at_least_defaults_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertNull($builder->getVersionMin());
    }

    /**
     * Test that versionAtMost() sets and returns the builder instance
     */
    public function test_version_at_most_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->versionAtMost(10);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals(10, $builder->getVersionMax());
    }

    /**
     * Test that versionAtMost() defaults to null
     */
    public function test_version_at_most_defaults_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertNull($builder->getVersionMax());
    }

    /**
     * Test that preferEnvironments() sets and returns the builder instance
     */
    public function test_prefer_environments_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->preferEnvironments(['prod', 'staging']);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals(['prod', 'staging'], $builder->getPreferEnvironments());
    }

    /**
     * Test that preferEnvironments() normalizes and filters values
     */
    public function test_prefer_environments_normalizes_and_filters_values(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->preferEnvironments(['prod', 123, '', 'staging', null, 'test']);

        $envs = $builder->getPreferEnvironments();
        $this->assertEquals(['prod', '123', 'staging', 'test'], $envs);
    }

    /**
     * Test that preferEnvironments() handles empty array
     */
    public function test_prefer_environments_handles_empty_array(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->preferEnvironments([]);
        $this->assertEmpty($builder->getPreferEnvironments());
    }

    /**
     * Test that preferEnvironments() defaults to empty array
     */
    public function test_prefer_environments_defaults_to_empty_array(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertIsArray($builder->getPreferEnvironments());
        $this->assertEmpty($builder->getPreferEnvironments());
    }

    /**
     * Test that preferChannels() sets and returns the builder instance
     */
    public function test_prefer_channels_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->preferChannels(['web', 'api']);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals(['web', 'api'], $builder->getPreferChannels());
    }

    /**
     * Test that preferChannels() normalizes and filters values
     */
    public function test_prefer_channels_normalizes_and_filters_values(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->preferChannels(['web', 123, '', 'api', null, 'mobile']);

        $channels = $builder->getPreferChannels();
        $this->assertEquals(['web', '123', 'api', 'mobile'], $channels);
    }

    /**
     * Test that preferChannels() handles empty array
     */
    public function test_prefer_channels_handles_empty_array(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->preferChannels([]);
        $this->assertEmpty($builder->getPreferChannels());
    }

    /**
     * Test that preferChannels() defaults to empty array
     */
    public function test_prefer_channels_defaults_to_empty_array(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertIsArray($builder->getPreferChannels());
        $this->assertEmpty($builder->getPreferChannels());
    }

    /**
     * Test that fallbackCascade() sets and returns the builder instance
     */
    public function test_fallback_cascade_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $steps = [
            FlowPickerBuilder::FB_DROP_CHANNEL,
            FlowPickerBuilder::FB_DROP_ENVIRONMENT,
        ];
        $result = $builder->fallbackCascade($steps);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals($steps, $builder->getFallbackCascade());
    }

    /**
     * Test that fallbackCascade() filters out invalid steps
     */
    public function test_fallback_cascade_filters_out_invalid_steps(): void
    {
        $builder = new FlowPickerBuilder();
        $steps = [
            FlowPickerBuilder::FB_DROP_CHANNEL,
            'invalid_step',
            FlowPickerBuilder::FB_DROP_ENVIRONMENT,
            'another_invalid',
            FlowPickerBuilder::FB_IGNORE_TIMEWINDOW,
        ];
        $builder->fallbackCascade($steps);

        $cascade = $builder->getFallbackCascade();
        $this->assertEquals([
            FlowPickerBuilder::FB_DROP_CHANNEL,
            FlowPickerBuilder::FB_DROP_ENVIRONMENT,
            FlowPickerBuilder::FB_IGNORE_TIMEWINDOW,
        ], $cascade);
    }

    /**
     * Test that fallbackCascade() accepts all valid constants
     */
    public function test_fallback_cascade_accepts_all_valid_constants(): void
    {
        $builder = new FlowPickerBuilder();
        $steps = [
            FlowPickerBuilder::FB_DROP_CHANNEL,
            FlowPickerBuilder::FB_DROP_ENVIRONMENT,
            FlowPickerBuilder::FB_IGNORE_TIMEWINDOW,
            FlowPickerBuilder::FB_DISABLE_ROLLOUT,
            FlowPickerBuilder::FB_DROP_REQUIRE_DEFAULT,
        ];
        $builder->fallbackCascade($steps);

        $cascade = $builder->getFallbackCascade();
        $this->assertEquals($steps, $cascade);
    }

    /**
     * Test that fallbackCascade() handles empty array
     */
    public function test_fallback_cascade_handles_empty_array(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->fallbackCascade([]);
        $this->assertEmpty($builder->getFallbackCascade());
    }

    /**
     * Test that fallbackCascade() defaults to empty array
     */
    public function test_fallback_cascade_defaults_to_empty_array(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertIsArray($builder->getFallbackCascade());
        $this->assertEmpty($builder->getFallbackCascade());
    }

    /**
     * Test that forceFlowIdResolver() sets and returns the builder instance
     */
    public function test_force_flow_id_resolver_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $resolver = fn (Model $m) => $m->getKey();
        $result = $builder->forceFlowIdResolver($resolver);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertSame($resolver, $builder->getForceFlowIdResolver());
    }

    /**
     * Test that forceFlowIdResolver() defaults to null
     */
    public function test_force_flow_id_resolver_defaults_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertNull($builder->getForceFlowIdResolver());
    }

    /**
     * Test that forceFlowIdResolver() can be called with a closure
     */
    public function test_force_flow_id_resolver_accepts_closure(): void
    {
        $builder = new FlowPickerBuilder();
        $resolver = function (Model $model): ?int {
            return $model->getAttribute('forced_flow_id');
        };
        $builder->forceFlowIdResolver($resolver);

        $this->assertIsCallable($builder->getForceFlowIdResolver());
    }

    /**
     * Test that returnCandidates() sets and returns the builder instance
     */
    public function test_return_candidates_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->returnCandidates(true);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertTrue($builder->shouldReturnCandidates());
    }

    /**
     * Test that returnCandidates() defaults to false
     */
    public function test_return_candidates_defaults_to_false(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertFalse($builder->shouldReturnCandidates());
    }

    /**
     * Test that returnCandidates() defaults to true when called without arguments
     */
    public function test_return_candidates_defaults_to_true_when_called_without_args(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->returnCandidates();
        $this->assertTrue($builder->shouldReturnCandidates());
    }

    /**
     * Test that candidatesLimit() sets and returns the builder instance
     */
    public function test_candidates_limit_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->candidatesLimit(10);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertEquals(10, $builder->getCandidatesLimit());
    }

    /**
     * Test that candidatesLimit() can be set to null
     */
    public function test_candidates_limit_can_be_set_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->candidatesLimit(10);
        $this->assertEquals(10, $builder->getCandidatesLimit());

        $builder->candidatesLimit(null);
        $this->assertNull($builder->getCandidatesLimit());
    }

    /**
     * Test that candidatesLimit() defaults to null
     */
    public function test_candidates_limit_defaults_to_null(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertNull($builder->getCandidatesLimit());
    }

    /**
     * Test that cacheInRequest() sets and returns the builder instance
     */
    public function test_cache_in_request_sets_and_returns_builder(): void
    {
        $builder = new FlowPickerBuilder();
        $result = $builder->cacheInRequest(true);

        $this->assertInstanceOf(FlowPickerBuilder::class, $result);
        $this->assertSame($result, $builder);
        $this->assertTrue($builder->shouldCacheInRequest());
    }

    /**
     * Test that cacheInRequest() defaults to false
     */
    public function test_cache_in_request_defaults_to_false(): void
    {
        $builder = new FlowPickerBuilder();
        $this->assertFalse($builder->shouldCacheInRequest());
    }

    /**
     * Test that cacheInRequest() defaults to true when called without arguments
     */
    public function test_cache_in_request_defaults_to_true_when_called_without_args(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->cacheInRequest();
        $this->assertTrue($builder->shouldCacheInRequest());
    }

    /**
     * Test method chaining with multiple methods
     */
    public function test_method_chaining_works_correctly(): void
    {
        $builder = new FlowPickerBuilder();
        $now = Carbon::now('UTC');

        $result = $builder->subjectType(Model::class)
            ->subjectScope('tenant123')
            ->subjectCollection('epic')
            ->environment('prod')
            ->channel('web')
            ->onlyActive(true)
            ->ignoreTimeWindow(false)
            ->timeNow($now)
            ->evaluateRollout(true)
            ->rolloutNamespace('order')
            ->rolloutSalt('tests')
            ->rolloutKeyResolver(fn ($m) => (string) $m->getKey())
            ->requireDefault(false)
            ->includeFlows([1, 2, 3])
            ->excludeFlows([4, 5])
            ->preferFlows([1, 2])
            ->versionEquals(5)
            ->preferEnvironments(['prod', 'staging'])
            ->preferChannels(['web', 'api'])
            ->fallbackCascade([FlowPickerBuilder::FB_DROP_CHANNEL])
            ->matchStrategy(FlowPickerBuilder::STRATEGY_BEST)
            ->cacheInRequest(true);

        $this->assertSame($builder, $result);
        $this->assertEquals(Model::class, $builder->getSubjectType());
        $this->assertEquals('tenant123', $builder->getSubjectScope());
        $this->assertEquals('epic', $builder->getSubjectCollection());
        $this->assertEquals('prod', $builder->getEnvironment());
        $this->assertEquals('web', $builder->getChannel());
        $this->assertTrue($builder->isOnlyActive());
        $this->assertFalse($builder->shouldIgnoreTimeWindow());
        $this->assertSame($now, $builder->getNowUtc());
        $this->assertTrue($builder->shouldEvaluateRollout());
        $this->assertEquals('order', $builder->getRolloutNamespace());
        $this->assertEquals('tests', $builder->getRolloutSalt());
        $this->assertNotNull($builder->getRolloutKeyResolver());
        $this->assertFalse($builder->isRequireDefault());
        $this->assertEquals([1, 2, 3], $builder->getIncludeFlowIds());
        $this->assertEquals([4, 5], $builder->getExcludeFlowIds());
        $this->assertEquals([1, 2], $builder->getPreferFlowIds());
        $this->assertEquals(5, $builder->getVersionEquals());
        $this->assertEquals(['prod', 'staging'], $builder->getPreferEnvironments());
        $this->assertEquals(['web', 'api'], $builder->getPreferChannels());
        $this->assertEquals([FlowPickerBuilder::FB_DROP_CHANNEL], $builder->getFallbackCascade());
        $this->assertEquals(FlowPickerBuilder::STRATEGY_BEST, $builder->getMatchStrategy());
        $this->assertTrue($builder->shouldCacheInRequest());
    }

    /**
     * Test that constants are defined correctly
     */
    public function test_constants_are_defined_correctly(): void
    {
        $this->assertEquals('best', FlowPickerBuilder::STRATEGY_BEST);
        $this->assertEquals('first', FlowPickerBuilder::STRATEGY_FIRST);
        $this->assertEquals('drop_channel', FlowPickerBuilder::FB_DROP_CHANNEL);
        $this->assertEquals('drop_environment', FlowPickerBuilder::FB_DROP_ENVIRONMENT);
        $this->assertEquals('ignore_timewindow', FlowPickerBuilder::FB_IGNORE_TIMEWINDOW);
        $this->assertEquals('disable_rollout', FlowPickerBuilder::FB_DISABLE_ROLLOUT);
        $this->assertEquals('drop_require_default', FlowPickerBuilder::FB_DROP_REQUIRE_DEFAULT);
    }

    /**
     * Test that versionEquals() overrides versionMin and versionMax
     */
    public function test_version_equals_overrides_version_min_and_max(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->versionAtLeast(3)->versionAtMost(10);
        $this->assertEquals(3, $builder->getVersionMin());
        $this->assertEquals(10, $builder->getVersionMax());

        $builder->versionEquals(5);
        $this->assertEquals(5, $builder->getVersionEquals());
        // versionMin and versionMax should still be set (they're just ignored in logic)
        $this->assertEquals(3, $builder->getVersionMin());
        $this->assertEquals(10, $builder->getVersionMax());
    }

    /**
     * Test that includeFlows() handles negative numbers
     */
    public function test_include_flows_handles_negative_numbers(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->includeFlows([-1, -2, 3]);
        $ids = $builder->getIncludeFlowIds();
        $this->assertEquals([-1, -2, 3], $ids);
    }

    /**
     * Test that excludeFlows() handles negative numbers
     */
    public function test_exclude_flows_handles_negative_numbers(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->excludeFlows([-1, -2, 3]);
        $ids = $builder->getExcludeFlowIds();
        $this->assertEquals([-1, -2, 3], $ids);
    }

    /**
     * Test that preferFlows() handles negative numbers
     */
    public function test_prefer_flows_handles_negative_numbers(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->preferFlows([-1, -2, 3]);
        $ids = $builder->getPreferFlowIds();
        $this->assertEquals([-1, -2, 3], $ids);
    }

    /**
     * Test that includeFlows() handles zero
     */
    public function test_include_flows_handles_zero(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->includeFlows([0, 1, 2]);
        $ids = $builder->getIncludeFlowIds();
        $this->assertEquals([0, 1, 2], $ids);
    }

    /**
     * Test that fallbackCascade() preserves order
     */
    public function test_fallback_cascade_preserves_order(): void
    {
        $builder = new FlowPickerBuilder();
        $steps = [
            FlowPickerBuilder::FB_DROP_CHANNEL,
            FlowPickerBuilder::FB_DROP_ENVIRONMENT,
            FlowPickerBuilder::FB_IGNORE_TIMEWINDOW,
            FlowPickerBuilder::FB_DISABLE_ROLLOUT,
            FlowPickerBuilder::FB_DROP_REQUIRE_DEFAULT,
        ];
        $builder->fallbackCascade($steps);

        $cascade = $builder->getFallbackCascade();
        $this->assertEquals($steps, $cascade);
    }

    /**
     * Test that fallbackCascade() handles duplicate valid steps
     */
    public function test_fallback_cascade_handles_duplicate_valid_steps(): void
    {
        $builder = new FlowPickerBuilder();
        $steps = [
            FlowPickerBuilder::FB_DROP_CHANNEL,
            FlowPickerBuilder::FB_DROP_CHANNEL,
            FlowPickerBuilder::FB_DROP_ENVIRONMENT,
        ];
        $builder->fallbackCascade($steps);

        $cascade = $builder->getFallbackCascade();
        $this->assertEquals([
            FlowPickerBuilder::FB_DROP_CHANNEL,
            FlowPickerBuilder::FB_DROP_CHANNEL,
            FlowPickerBuilder::FB_DROP_ENVIRONMENT,
        ], $cascade);
    }

    /**
     * Test that preferEnvironments() removes empty strings
     */
    public function test_prefer_environments_removes_empty_strings(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->preferEnvironments(['prod', '', 'staging', '']);
        $envs = $builder->getPreferEnvironments();
        $this->assertEquals(['prod', 'staging'], $envs);
    }

    /**
     * Test that preferChannels() removes empty strings
     */
    public function test_prefer_channels_removes_empty_strings(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->preferChannels(['web', '', 'api', '']);
        $channels = $builder->getPreferChannels();
        $this->assertEquals(['web', 'api'], $channels);
    }

    /**
     * Test that where() callbacks are stored in order
     */
    public function test_where_callbacks_are_stored_in_order(): void
    {
        $builder = new FlowPickerBuilder();
        $callback1 = function (Builder $q, Model $m): void {
        };
        $callback2 = function (Builder $q, Model $m): void {
        };
        $callback3 = function (Builder $q, Model $m): void {
        };

        $builder->where($callback1)->where($callback2)->where($callback3);

        $callbacks = $builder->getWhereCallbacks();
        $this->assertCount(3, $callbacks);
        $this->assertSame($callback1, $callbacks[0]);
        $this->assertSame($callback2, $callbacks[1]);
        $this->assertSame($callback3, $callbacks[2]);
    }

    /**
     * Test that orderBy() can be called multiple times (overwrites)
     */
    public function test_order_by_overwrites_previous_callback(): void
    {
        $builder = new FlowPickerBuilder();
        $callback1 = function (Builder $q): void {
            $q->orderBy('field1', 'asc');
        };
        $callback2 = function (Builder $q): void {
            $q->orderBy('field2', 'desc');
        };

        $builder->orderBy($callback1);
        $this->assertSame($callback1, $builder->getOrderingCallback());

        $builder->orderBy($callback2);
        $this->assertSame($callback2, $builder->getOrderingCallback());
        $this->assertNotSame($callback1, $builder->getOrderingCallback());
    }

    /**
     * Test that orderByDefault() can be called multiple times
     */
    public function test_order_by_default_can_be_called_multiple_times(): void
    {
        $builder = new FlowPickerBuilder();
        $builder->orderByDefault();
        $callback1 = $builder->getOrderingCallback();

        $builder->orderByDefault();
        $callback2 = $builder->getOrderingCallback();

        // Both should be callable and equivalent in behavior
        $this->assertIsCallable($callback1);
        $this->assertIsCallable($callback2);
    }

    /**
     * Test that rolloutKeyResolver() can return null
     */
    public function test_rollout_key_resolver_can_return_null(): void
    {
        $builder = new FlowPickerBuilder();
        $resolver = function (Model $model): ?string {
            return null;
        };
        $builder->rolloutKeyResolver($resolver);

        $mockModel = Mockery::mock(Model::class);
        $result = $resolver($mockModel);
        $this->assertNull($result);
    }

    /**
     * Test that forceFlowIdResolver() can return null
     */
    public function test_force_flow_id_resolver_can_return_null(): void
    {
        $builder = new FlowPickerBuilder();
        $resolver = function (Model $model): ?int {
            return null;
        };
        $builder->forceFlowIdResolver($resolver);

        $mockModel = Mockery::mock(Model::class);
        $result = $resolver($mockModel);
        $this->assertNull($result);
    }

    /**
     * Test that all getters return correct default values for a new instance
     */
    public function test_all_getters_return_correct_default_values(): void
    {
        $builder = new FlowPickerBuilder();

        $this->assertNull($builder->getSubjectType());
        $this->assertNull($builder->getSubjectScope());
        $this->assertNull($builder->getSubjectCollection());
        $this->assertNull($builder->getEnvironment());
        $this->assertNull($builder->getChannel());
        $this->assertEmpty($builder->getPreferEnvironments());
        $this->assertEmpty($builder->getPreferChannels());
        $this->assertTrue($builder->isOnlyActive());
        $this->assertFalse($builder->shouldIgnoreTimeWindow());
        $this->assertNull($builder->getNowUtc());
        $this->assertTrue($builder->shouldEvaluateRollout());
        $this->assertNull($builder->getRolloutNamespace());
        $this->assertNull($builder->getRolloutSalt());
        $this->assertNull($builder->getRolloutKeyResolver());
        $this->assertEmpty($builder->getWhereCallbacks());
        $this->assertNull($builder->getOrderingCallback());
        $this->assertEquals(FlowPickerBuilder::STRATEGY_BEST, $builder->getMatchStrategy());
        $this->assertFalse($builder->isRequireDefault());
        $this->assertEmpty($builder->getIncludeFlowIds());
        $this->assertEmpty($builder->getExcludeFlowIds());
        $this->assertEmpty($builder->getPreferFlowIds());
        $this->assertNull($builder->getVersionEquals());
        $this->assertNull($builder->getVersionMin());
        $this->assertNull($builder->getVersionMax());
        $this->assertEmpty($builder->getFallbackCascade());
        $this->assertNull($builder->getForceFlowIdResolver());
        $this->assertFalse($builder->shouldReturnCandidates());
        $this->assertNull($builder->getCandidatesLimit());
        $this->assertFalse($builder->shouldCacheInRequest());
    }
}
