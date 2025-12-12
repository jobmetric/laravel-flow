<?php

namespace JobMetric\Flow\Tests\Unit\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use JobMetric\Flow\DTO\TransitionResult;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Tests\TestCase;
use Mockery;

/**
 * Comprehensive tests for FlowTaskContext
 *
 * This class holds runtime data for flow task execution, including the subject model,
 * transition result, payload, authenticated user, and cached configuration.
 *
 * These tests cover constructor, all getter methods, replaceConfig method, default values,
 * null handling, array operations, and edge cases.
 */
class FlowTaskContextTest extends TestCase
{
    /**
     * Test that constructor accepts all required parameters
     */
    public function test_constructor_accepts_all_required_parameters(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $payload = ['key' => 'value'];
        $user = Mockery::mock(Authenticatable::class);

        $context = new FlowTaskContext($subject, $result, $payload, $user);

        $this->assertInstanceOf(FlowTaskContext::class, $context);
        $this->assertSame($subject, $context->subject());
        $this->assertSame($result, $context->result());
        $this->assertSame($payload, $context->payload());
        $this->assertSame($user, $context->user());
    }

    /**
     * Test that constructor accepts optional payload (defaults to empty array)
     */
    public function test_constructor_accepts_optional_payload(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();

        $context = new FlowTaskContext($subject, $result);

        $this->assertInstanceOf(FlowTaskContext::class, $context);
        $this->assertSame($subject, $context->subject());
        $this->assertSame($result, $context->result());
        $this->assertIsArray($context->payload());
        $this->assertEmpty($context->payload());
    }

    /**
     * Test that constructor accepts optional user (defaults to null)
     */
    public function test_constructor_accepts_optional_user(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $payload = ['key' => 'value'];

        $context = new FlowTaskContext($subject, $result, $payload);

        $this->assertInstanceOf(FlowTaskContext::class, $context);
        $this->assertSame($subject, $context->subject());
        $this->assertSame($result, $context->result());
        $this->assertSame($payload, $context->payload());
        $this->assertNull($context->user());
    }

    /**
     * Test that constructor accepts both optional parameters
     */
    public function test_constructor_accepts_both_optional_parameters(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();

        $context = new FlowTaskContext($subject, $result);

        $this->assertInstanceOf(FlowTaskContext::class, $context);
        $this->assertSame($subject, $context->subject());
        $this->assertSame($result, $context->result());
        $this->assertIsArray($context->payload());
        $this->assertEmpty($context->payload());
        $this->assertNull($context->user());
    }

    /**
     * Test that subject() returns the correct model instance
     */
    public function test_subject_returns_correct_model_instance(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();

        $context = new FlowTaskContext($subject, $result);

        $this->assertSame($subject, $context->subject());
        $this->assertInstanceOf(Model::class, $context->subject());
    }

    /**
     * Test that result() returns the correct TransitionResult instance
     */
    public function test_result_returns_correct_transition_result_instance(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = TransitionResult::success();

        $context = new FlowTaskContext($subject, $result);

        $this->assertSame($result, $context->result());
        $this->assertInstanceOf(TransitionResult::class, $context->result());
    }

    /**
     * Test that payload() returns the correct array
     */
    public function test_payload_returns_correct_array(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $payload = ['key1' => 'value1', 'key2' => 'value2', 'nested' => ['key' => 'value']];

        $context = new FlowTaskContext($subject, $result, $payload);

        $this->assertSame($payload, $context->payload());
        $this->assertIsArray($context->payload());
        $this->assertEquals('value1', $context->payload()['key1']);
        $this->assertEquals('value2', $context->payload()['key2']);
        $this->assertIsArray($context->payload()['nested']);
    }

    /**
     * Test that payload() returns empty array when not provided
     */
    public function test_payload_returns_empty_array_when_not_provided(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();

        $context = new FlowTaskContext($subject, $result);

        $this->assertIsArray($context->payload());
        $this->assertEmpty($context->payload());
    }

    /**
     * Test that payload() returns a copy, not a reference
     */
    public function test_payload_returns_copy_not_reference(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $payload = ['key' => 'value'];

        $context = new FlowTaskContext($subject, $result, $payload);

        $returnedPayload = $context->payload();
        $returnedPayload['new_key'] = 'new_value';

        $this->assertArrayNotHasKey('new_key', $context->payload());
        $this->assertArrayHasKey('new_key', $returnedPayload);
    }

    /**
     * Test that user() returns the correct Authenticatable instance
     */
    public function test_user_returns_correct_authenticatable_instance(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $user = Mockery::mock(Authenticatable::class);

        $context = new FlowTaskContext($subject, $result, [], $user);

        $this->assertSame($user, $context->user());
        $this->assertInstanceOf(Authenticatable::class, $context->user());
    }

    /**
     * Test that user() returns null when not provided
     */
    public function test_user_returns_null_when_not_provided(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();

        $context = new FlowTaskContext($subject, $result);

        $this->assertNull($context->user());
    }

    /**
     * Test that config() returns empty array by default
     */
    public function test_config_returns_empty_array_by_default(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();

        $context = new FlowTaskContext($subject, $result);

        $this->assertIsArray($context->config());
        $this->assertEmpty($context->config());
    }

    /**
     * Test that replaceConfig() sets the configuration and returns the instance
     */
    public function test_replace_config_sets_configuration_and_returns_instance(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $config = ['key1' => 'value1', 'key2' => 'value2'];
        $result = $context->replaceConfig($config);

        $this->assertInstanceOf(FlowTaskContext::class, $result);
        $this->assertSame($context, $result);
        $this->assertEquals($config, $context->config());
    }

    /**
     * Test that replaceConfig() can be called multiple times
     */
    public function test_replace_config_can_be_called_multiple_times(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $config1 = ['key1' => 'value1'];
        $context->replaceConfig($config1);
        $this->assertEquals($config1, $context->config());

        $config2 = ['key2' => 'value2', 'key3' => 'value3'];
        $context->replaceConfig($config2);
        $this->assertEquals($config2, $context->config());
        $this->assertNotEquals($config1, $context->config());
    }

    /**
     * Test that replaceConfig() accepts empty array
     */
    public function test_replace_config_accepts_empty_array(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $context->replaceConfig(['key' => 'value']);
        $this->assertNotEmpty($context->config());

        $context->replaceConfig([]);
        $this->assertIsArray($context->config());
        $this->assertEmpty($context->config());
    }

    /**
     * Test that replaceConfig() accepts nested arrays
     */
    public function test_replace_config_accepts_nested_arrays(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $config = [
            'key1' => 'value1',
            'nested' => [
                'key2' => 'value2',
                'deep' => [
                    'key3' => 'value3',
                ],
            ],
        ];

        $context->replaceConfig($config);
        $this->assertEquals($config, $context->config());
        $this->assertIsArray($context->config()['nested']);
        $this->assertIsArray($context->config()['nested']['deep']);
    }

    /**
     * Test that config() returns a copy, not a reference
     */
    public function test_config_returns_copy_not_reference(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $config = ['key' => 'value'];
        $context->replaceConfig($config);

        $returnedConfig = $context->config();
        $returnedConfig['new_key'] = 'new_value';

        $this->assertArrayNotHasKey('new_key', $context->config());
        $this->assertArrayHasKey('new_key', $returnedConfig);
    }

    /**
     * Test that replaceConfig() supports method chaining
     */
    public function test_replace_config_supports_method_chaining(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $config1 = ['key1' => 'value1'];
        $config2 = ['key2' => 'value2'];

        $result = $context->replaceConfig($config1)->replaceConfig($config2);

        $this->assertSame($context, $result);
        $this->assertEquals($config2, $context->config());
    }

    /**
     * Test that payload() can contain various data types
     */
    public function test_payload_can_contain_various_data_types(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $payload = [
            'string' => 'value',
            'integer' => 123,
            'float' => 45.67,
            'boolean' => true,
            'null' => null,
            'array' => [1, 2, 3],
            'nested' => ['key' => 'value'],
        ];

        $context = new FlowTaskContext($subject, $result, $payload);

        $this->assertEquals('value', $context->payload()['string']);
        $this->assertEquals(123, $context->payload()['integer']);
        $this->assertEquals(45.67, $context->payload()['float']);
        $this->assertTrue($context->payload()['boolean']);
        $this->assertNull($context->payload()['null']);
        $this->assertIsArray($context->payload()['array']);
        $this->assertIsArray($context->payload()['nested']);
    }

    /**
     * Test that config() can contain various data types
     */
    public function test_config_can_contain_various_data_types(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $config = [
            'string' => 'value',
            'integer' => 123,
            'float' => 45.67,
            'boolean' => true,
            'null' => null,
            'array' => [1, 2, 3],
            'nested' => ['key' => 'value'],
        ];

        $context->replaceConfig($config);

        $this->assertEquals('value', $context->config()['string']);
        $this->assertEquals(123, $context->config()['integer']);
        $this->assertEquals(45.67, $context->config()['float']);
        $this->assertTrue($context->config()['boolean']);
        $this->assertNull($context->config()['null']);
        $this->assertIsArray($context->config()['array']);
        $this->assertIsArray($context->config()['nested']);
    }

    /**
     * Test that subject() always returns the same instance
     */
    public function test_subject_always_returns_same_instance(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $subject1 = $context->subject();
        $subject2 = $context->subject();

        $this->assertSame($subject1, $subject2);
        $this->assertSame($subject, $subject1);
    }

    /**
     * Test that result() always returns the same instance
     */
    public function test_result_always_returns_same_instance(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $result1 = $context->result();
        $result2 = $context->result();

        $this->assertSame($result1, $result2);
        $this->assertSame($result, $result1);
    }

    /**
     * Test that user() always returns the same instance when set
     */
    public function test_user_always_returns_same_instance_when_set(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $user = Mockery::mock(Authenticatable::class);
        $context = new FlowTaskContext($subject, $result, [], $user);

        $user1 = $context->user();
        $user2 = $context->user();

        $this->assertSame($user1, $user2);
        $this->assertSame($user, $user1);
    }

    /**
     * Test that payload() can be modified externally without affecting context
     */
    public function test_payload_can_be_modified_externally_without_affecting_context(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $payload = ['key' => 'value'];
        $context = new FlowTaskContext($subject, $result, $payload);

        $payload['new_key'] = 'new_value';

        $this->assertArrayNotHasKey('new_key', $context->payload());
        $this->assertArrayHasKey('new_key', $payload);
    }

    /**
     * Test that config() can be modified externally without affecting context
     */
    public function test_config_can_be_modified_externally_without_affecting_context(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $config = ['key' => 'value'];
        $context->replaceConfig($config);

        $config['new_key'] = 'new_value';

        $this->assertArrayNotHasKey('new_key', $context->config());
        $this->assertArrayHasKey('new_key', $config);
    }

    /**
     * Test that result() can be modified and changes are reflected
     */
    public function test_result_can_be_modified_and_changes_are_reflected(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = TransitionResult::success();
        $context = new FlowTaskContext($subject, $result);

        $this->assertTrue($context->result()->isSuccess());

        $context->result()->markFailed();
        $this->assertFalse($context->result()->isSuccess());
    }

    /**
     * Test that subject() can be used to access model properties
     */
    public function test_subject_can_be_used_to_access_model_properties(): void
    {
        $subject = Mockery::mock(Model::class);

        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $this->assertSame($subject, $context->subject());
        $this->assertInstanceOf(Model::class, $context->subject());
    }

    /**
     * Test that payload() handles large arrays
     */
    public function test_payload_handles_large_arrays(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $payload = [];
        for ($i = 0; $i < 100; $i++) {
            $payload["key_$i"] = "value_$i";
        }

        $context = new FlowTaskContext($subject, $result, $payload);

        $this->assertCount(100, $context->payload());
        $this->assertEquals('value_50', $context->payload()['key_50']);
    }

    /**
     * Test that config() handles large arrays
     */
    public function test_config_handles_large_arrays(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $config = [];
        for ($i = 0; $i < 100; $i++) {
            $config["key_$i"] = "value_$i";
        }

        $context->replaceConfig($config);

        $this->assertCount(100, $context->config());
        $this->assertEquals('value_50', $context->config()['key_50']);
    }

    /**
     * Test that payload() can contain objects
     */
    public function test_payload_can_contain_objects(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $object = new \stdClass();
        $object->property = 'value';
        $payload = ['object' => $object];

        $context = new FlowTaskContext($subject, $result, $payload);

        $this->assertIsObject($context->payload()['object']);
        $this->assertEquals('value', $context->payload()['object']->property);
    }

    /**
     * Test that config() can contain objects
     */
    public function test_config_can_contain_objects(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $object = new \stdClass();
        $object->property = 'value';
        $config = ['object' => $object];

        $context->replaceConfig($config);

        $this->assertIsObject($context->config()['object']);
        $this->assertEquals('value', $context->config()['object']->property);
    }

    /**
     * Test that replaceConfig() overwrites previous configuration
     */
    public function test_replace_config_overwrites_previous_configuration(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $config1 = ['key1' => 'value1', 'key2' => 'value2'];
        $context->replaceConfig($config1);
        $this->assertEquals($config1, $context->config());

        $config2 = ['key3' => 'value3'];
        $context->replaceConfig($config2);
        $this->assertEquals($config2, $context->config());
        $this->assertArrayNotHasKey('key1', $context->config());
        $this->assertArrayNotHasKey('key2', $context->config());
    }

    /**
     * Test that payload() can be empty array
     */
    public function test_payload_can_be_empty_array(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result, []);

        $this->assertIsArray($context->payload());
        $this->assertEmpty($context->payload());
    }

    /**
     * Test that config() can be set to empty array
     */
    public function test_config_can_be_set_to_empty_array(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $context->replaceConfig(['key' => 'value']);
        $this->assertNotEmpty($context->config());

        $context->replaceConfig([]);
        $this->assertIsArray($context->config());
        $this->assertEmpty($context->config());
    }

    /**
     * Test that all getters work correctly together
     */
    public function test_all_getters_work_correctly_together(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = TransitionResult::success();
        $payload = ['key' => 'value'];
        $user = Mockery::mock(Authenticatable::class);
        $context = new FlowTaskContext($subject, $result, $payload, $user);

        $this->assertSame($subject, $context->subject());
        $this->assertSame($result, $context->result());
        $this->assertSame($payload, $context->payload());
        $this->assertSame($user, $context->user());
        $this->assertIsArray($context->config());
        $this->assertEmpty($context->config());
    }

    /**
     * Test that replaceConfig() works with all getters
     */
    public function test_replace_config_works_with_all_getters(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = TransitionResult::success();
        $payload = ['key' => 'value'];
        $user = Mockery::mock(Authenticatable::class);
        $context = new FlowTaskContext($subject, $result, $payload, $user);

        $config = ['config_key' => 'config_value'];
        $context->replaceConfig($config);

        $this->assertSame($subject, $context->subject());
        $this->assertSame($result, $context->result());
        $this->assertSame($payload, $context->payload());
        $this->assertSame($user, $context->user());
        $this->assertEquals($config, $context->config());
    }

    /**
     * Test that payload() preserves array keys
     */
    public function test_payload_preserves_array_keys(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $payload = [
            'string_key' => 'value1',
            'numeric_key' => 'value2',
            0 => 'value3',
            '1' => 'value4',
        ];

        $context = new FlowTaskContext($subject, $result, $payload);

        $this->assertArrayHasKey('string_key', $context->payload());
        $this->assertArrayHasKey('numeric_key', $context->payload());
        $this->assertArrayHasKey(0, $context->payload());
        $this->assertArrayHasKey('1', $context->payload());
    }

    /**
     * Test that config() preserves array keys
     */
    public function test_config_preserves_array_keys(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $config = [
            'string_key' => 'value1',
            'numeric_key' => 'value2',
            0 => 'value3',
            '1' => 'value4',
        ];

        $context->replaceConfig($config);

        $this->assertArrayHasKey('string_key', $context->config());
        $this->assertArrayHasKey('numeric_key', $context->config());
        $this->assertArrayHasKey(0, $context->config());
        $this->assertArrayHasKey('1', $context->config());
    }

    /**
     * Test that user() can be null and still work correctly
     */
    public function test_user_can_be_null_and_still_work_correctly(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $payload = ['key' => 'value'];
        $context = new FlowTaskContext($subject, $result, $payload, null);

        $this->assertNull($context->user());
        $this->assertSame($subject, $context->subject());
        $this->assertSame($result, $context->result());
        $this->assertSame($payload, $context->payload());
    }

    /**
     * Test that result() can be a failed result
     */
    public function test_result_can_be_a_failed_result(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = TransitionResult::failure();
        $context = new FlowTaskContext($subject, $result);

        $this->assertSame($result, $context->result());
        $this->assertFalse($context->result()->isSuccess());
    }

    /**
     * Test that result() can be a success result
     */
    public function test_result_can_be_a_success_result(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = TransitionResult::success();
        $context = new FlowTaskContext($subject, $result);

        $this->assertSame($result, $context->result());
        $this->assertTrue($context->result()->isSuccess());
    }

    /**
     * Test that replaceConfig() returns static for method chaining
     */
    public function test_replace_config_returns_static_for_method_chaining(): void
    {
        $subject = Mockery::mock(Model::class);
        $result = new TransitionResult();
        $context = new FlowTaskContext($subject, $result);

        $config1 = ['key1' => 'value1'];
        $config2 = ['key2' => 'value2'];

        $result = $context->replaceConfig($config1);
        $this->assertInstanceOf(FlowTaskContext::class, $result);

        $result = $context->replaceConfig($config2);
        $this->assertInstanceOf(FlowTaskContext::class, $result);
        $this->assertSame($context, $result);
    }
}

