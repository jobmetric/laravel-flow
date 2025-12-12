<?php

namespace JobMetric\Flow\Tests\Unit;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JobMetric\Flow\Casts\TaskDriverCast;
use JobMetric\Flow\Contracts\AbstractTaskDriver;
use JobMetric\Flow\Models\FlowTask;
use JobMetric\Flow\Tests\Stubs\Tasks\DummyActionTask;
use JobMetric\Flow\Tests\TestCase;
use Mockery;

/**
 * Comprehensive tests for TaskDriverCast
 *
 * These tests cover all possible scenarios for get, set, and serialize methods
 * to ensure the Cast works correctly.
 */
class TaskDriverCastTest extends TestCase
{
    protected TaskDriverCast $cast;
    protected FlowTask $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cast = new TaskDriverCast();
        $this->model = new FlowTask();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that TaskDriverCast implements CastsAttributes interface
     */
    public function test_implements_casts_attributes_interface(): void
    {
        $this->assertInstanceOf(CastsAttributes::class, $this->cast);
    }

    // ==================== Tests for get() method ====================

    /**
     * Test get() with null value
     */
    public function test_get_returns_null_when_value_is_null(): void
    {
        $result = $this->cast->get($this->model, 'driver', null, []);

        $this->assertNull($result);
    }

    /**
     * Test get() with empty string
     */
    public function test_get_returns_null_when_value_is_empty_string(): void
    {
        $result = $this->cast->get($this->model, 'driver', '', []);

        $this->assertNull($result);
    }

    /**
     * Test get() with valid class that extends AbstractTaskDriver
     */
    public function test_get_returns_instance_when_class_exists_and_extends_abstract_task_driver(): void
    {
        $fqcn = DummyActionTask::class;
        $attributes = ['id' => 1, 'flow_transition_id' => 10];

        $result = $this->cast->get($this->model, 'driver', $fqcn, $attributes);

        $this->assertInstanceOf(AbstractTaskDriver::class, $result);
        $this->assertInstanceOf(DummyActionTask::class, $result);
    }

    /**
     * Test get() with forward slash in namespace (should be converted to backslash)
     */
    public function test_get_normalizes_forward_slashes_to_backslashes(): void
    {
        $fqcnWithForwardSlash = str_replace('\\', '/', DummyActionTask::class);
        $attributes = ['id' => 1, 'flow_transition_id' => 10];

        $result = $this->cast->get($this->model, 'driver', $fqcnWithForwardSlash, $attributes);

        $this->assertInstanceOf(DummyActionTask::class, $result);
    }

    /**
     * Test get() with whitespace at beginning and end of string (should be trimmed)
     */
    public function test_get_trims_whitespace_from_class_name(): void
    {
        $fqcnWithSpaces = ' ' . DummyActionTask::class . ' ';
        $attributes = ['id' => 1, 'flow_transition_id' => 10];

        $result = $this->cast->get($this->model, 'driver', $fqcnWithSpaces, $attributes);

        $this->assertInstanceOf(DummyActionTask::class, $result);
    }

    /**
     * Test get() with non-existent class (should return null and log warning)
     */
    public function test_get_returns_null_and_logs_warning_when_class_does_not_exist(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Workflow task driver missing or invalid on retrieval.',
                Mockery::on(function ($context) {
                    return $context['stored_driver'] === 'NonExistent\\Class\\Name'
                        && $context['model'] === FlowTask::class
                        && $context['task_id'] === 5
                        && $context['transition_id'] === 20;
                })
            );

        $attributes = ['id' => 5, 'flow_transition_id' => 20];
        $result = $this->cast->get($this->model, 'driver', 'NonExistent\\Class\\Name', $attributes);

        $this->assertNull($result);
    }

    /**
     * Test get() with class that exists but does not extend AbstractTaskDriver
     */
    public function test_get_returns_null_and_logs_warning_when_class_does_not_extend_abstract_task_driver(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Workflow task driver missing or invalid on retrieval.',
                Mockery::on(function ($context) {
                    return $context['stored_driver'] === \stdClass::class
                        && $context['model'] === FlowTask::class;
                })
            );

        $attributes = ['id' => 1];
        $result = $this->cast->get($this->model, 'driver', \stdClass::class, $attributes);

        $this->assertNull($result);
    }

    /**
     * Test get() with attributes that don't have id
     */
    public function test_get_handles_missing_id_in_attributes(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Workflow task driver missing or invalid on retrieval.',
                Mockery::on(function ($context) {
                    return $context['task_id'] === null
                        && $context['transition_id'] === null;
                })
            );

        $result = $this->cast->get($this->model, 'driver', 'NonExistent\\Class', []);

        $this->assertNull($result);
    }

    /**
     * Test get() with attributes that only have id
     */
    public function test_get_handles_missing_transition_id_in_attributes(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Workflow task driver missing or invalid on retrieval.',
                Mockery::on(function ($context) {
                    return $context['task_id'] === 3
                        && $context['transition_id'] === null;
                })
            );

        $attributes = ['id' => 3];
        $result = $this->cast->get($this->model, 'driver', 'NonExistent\\Class', $attributes);

        $this->assertNull($result);
    }

    /**
     * Test get() resolves instance from container
     */
    public function test_get_resolves_instance_from_container(): void
    {
        $fqcn = DummyActionTask::class;
        $attributes = ['id' => 1];

        $result = $this->cast->get($this->model, 'driver', $fqcn, $attributes);

        // Verify that a new instance is created (not singleton)
        $result2 = $this->cast->get($this->model, 'driver', $fqcn, $attributes);
        $this->assertNotSame($result, $result2);
    }

    // ==================== Tests for set() method ====================

    /**
     * Test set() with null value
     */
    public function test_set_returns_null_when_value_is_null(): void
    {
        $result = $this->cast->set($this->model, 'driver', null, []);

        $this->assertNull($result);
    }

    /**
     * Test set() with empty string
     */
    public function test_set_returns_null_when_value_is_empty_string(): void
    {
        $result = $this->cast->set($this->model, 'driver', '', []);

        $this->assertNull($result);
    }

    /**
     * Test set() with valid class as string
     */
    public function test_set_returns_fqcn_when_valid_class_name_provided(): void
    {
        $fqcn = DummyActionTask::class;

        $result = $this->cast->set($this->model, 'driver', $fqcn, []);

        $this->assertEquals(DummyActionTask::class, $result);
    }

    /**
     * Test set() with valid class instance
     */
    public function test_set_returns_fqcn_when_valid_instance_provided(): void
    {
        $instance = new DummyActionTask();

        $result = $this->cast->set($this->model, 'driver', $instance, []);

        $this->assertEquals(DummyActionTask::class, $result);
    }

    /**
     * Test set() with forward slash in namespace
     */
    public function test_set_normalizes_forward_slashes_to_backslashes(): void
    {
        $fqcnWithForwardSlash = str_replace('\\', '/', DummyActionTask::class);

        $result = $this->cast->set($this->model, 'driver', $fqcnWithForwardSlash, []);

        $this->assertEquals(DummyActionTask::class, $result);
    }

    /**
     * Test set() with whitespace at beginning and end of string
     */
    public function test_set_trims_whitespace_from_class_name(): void
    {
        $fqcnWithSpaces = ' ' . DummyActionTask::class . ' ';

        $result = $this->cast->set($this->model, 'driver', $fqcnWithSpaces, []);

        $this->assertEquals(DummyActionTask::class, $result);
    }

    /**
     * Test set() with non-existent class (should throw exception)
     */
    public function test_set_throws_exception_when_class_does_not_exist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Task driver class [NonExistent\\Class\\Name] does not exist.");

        $this->cast->set($this->model, 'driver', 'NonExistent\\Class\\Name', []);
    }

    /**
     * Test set() with class that exists but does not extend AbstractTaskDriver
     */
    public function test_set_throws_exception_when_class_does_not_extend_abstract_task_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Task driver class [" . \stdClass::class . "] must extend " . AbstractTaskDriver::class . "."
        );

        $this->cast->set($this->model, 'driver', \stdClass::class, []);
    }

    /**
     * Test set() with value that is not string or object (should throw exception)
     */
    public function test_set_throws_exception_when_value_is_not_string_or_object(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Task driver value for [driver] must be a class name or instance.");

        $this->cast->set($this->model, 'driver', 123, []);
    }

    /**
     * Test set() with array (should throw exception)
     */
    public function test_set_throws_exception_when_value_is_array(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Task driver value for [driver] must be a class name or instance.");

        $this->cast->set($this->model, 'driver', [], []);
    }

    /**
     * Test set() with boolean (should throw exception)
     */
    public function test_set_throws_exception_when_value_is_boolean(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Task driver value for [driver] must be a class name or instance.");

        $this->cast->set($this->model, 'driver', true, []);
    }

    /**
     * Test set() with float (should throw exception)
     */
    public function test_set_throws_exception_when_value_is_float(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Task driver value for [driver] must be a class name or instance.");

        $this->cast->set($this->model, 'driver', 3.14, []);
    }

    /**
     * Test set() with instance of class that does not extend AbstractTaskDriver
     */
    public function test_set_throws_exception_when_instance_does_not_extend_abstract_task_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Task driver class [" . \stdClass::class . "] must extend " . AbstractTaskDriver::class . "."
        );

        $this->cast->set($this->model, 'driver', new \stdClass(), []);
    }

    /**
     * Test set() with valid class that has forward slash and whitespace
     */
    public function test_set_handles_forward_slash_and_whitespace_together(): void
    {
        $fqcn = ' ' . str_replace('\\', '/', DummyActionTask::class) . ' ';

        $result = $this->cast->set($this->model, 'driver', $fqcn, []);

        $this->assertEquals(DummyActionTask::class, $result);
    }

    // ==================== Tests for serialize() method ====================

    /**
     * Test serialize() with null value and empty attribute
     */
    public function test_serialize_returns_null_when_value_is_null_and_attribute_is_empty(): void
    {
        $result = $this->cast->serialize($this->model, 'driver', null, []);

        $this->assertNull($result);
    }

    /**
     * Test serialize() with null value but attribute has valid value
     */
    public function test_serialize_returns_normalized_fqcn_when_value_is_null_but_attribute_exists(): void
    {
        $fqcn = DummyActionTask::class;
        $attributes = ['driver' => $fqcn];

        $result = $this->cast->serialize($this->model, 'driver', null, $attributes);

        $this->assertEquals(DummyActionTask::class, $result);
    }

    /**
     * Test serialize() with null value and attribute with forward slash
     */
    public function test_serialize_normalizes_forward_slashes_when_value_is_null_but_attribute_exists(): void
    {
        $fqcnWithForwardSlash = str_replace('\\', '/', DummyActionTask::class);
        $attributes = ['driver' => $fqcnWithForwardSlash];

        $result = $this->cast->serialize($this->model, 'driver', null, $attributes);

        $this->assertEquals(DummyActionTask::class, $result);
    }

    /**
     * Test serialize() with null value and attribute with whitespace
     */
    public function test_serialize_trims_whitespace_when_value_is_null_but_attribute_exists(): void
    {
        $fqcnWithSpaces = ' ' . DummyActionTask::class . ' ';
        $attributes = ['driver' => $fqcnWithSpaces];

        $result = $this->cast->serialize($this->model, 'driver', null, $attributes);

        $this->assertEquals(DummyActionTask::class, $result);
    }

    /**
     * Test serialize() with null value and empty string attribute
     */
    public function test_serialize_returns_null_when_value_is_null_and_attribute_is_empty_string(): void
    {
        $attributes = ['driver' => ''];

        $result = $this->cast->serialize($this->model, 'driver', null, $attributes);

        $this->assertNull($result);
    }

    /**
     * Test serialize() with valid instance
     */
    public function test_serialize_returns_fqcn_when_value_is_valid_instance(): void
    {
        $instance = new DummyActionTask();

        $result = $this->cast->serialize($this->model, 'driver', $instance, []);

        $this->assertEquals(DummyActionTask::class, $result);
    }

    /**
     * Test serialize() with valid string
     */
    public function test_serialize_returns_normalized_fqcn_when_value_is_valid_string(): void
    {
        $fqcn = DummyActionTask::class;

        $result = $this->cast->serialize($this->model, 'driver', $fqcn, []);

        $this->assertEquals(DummyActionTask::class, $result);
    }

    /**
     * Test serialize() with string containing forward slash
     */
    public function test_serialize_normalizes_forward_slashes_when_value_is_string(): void
    {
        $fqcnWithForwardSlash = str_replace('\\', '/', DummyActionTask::class);

        $result = $this->cast->serialize($this->model, 'driver', $fqcnWithForwardSlash, []);

        $this->assertEquals(DummyActionTask::class, $result);
    }

    /**
     * Test serialize() with string containing whitespace
     */
    public function test_serialize_trims_whitespace_when_value_is_string(): void
    {
        $fqcnWithSpaces = ' ' . DummyActionTask::class . ' ';

        $result = $this->cast->serialize($this->model, 'driver', $fqcnWithSpaces, []);

        $this->assertEquals(DummyActionTask::class, $result);
    }

    /**
     * Test serialize() with value that is not string, object, or null
     */
    public function test_serialize_returns_null_when_value_is_not_string_object_or_null(): void
    {
        $result = $this->cast->serialize($this->model, 'driver', 123, []);

        $this->assertNull($result);
    }

    /**
     * Test serialize() with array
     */
    public function test_serialize_returns_null_when_value_is_array(): void
    {
        $result = $this->cast->serialize($this->model, 'driver', [], []);

        $this->assertNull($result);
    }

    /**
     * Test serialize() with boolean
     */
    public function test_serialize_returns_null_when_value_is_boolean(): void
    {
        $result = $this->cast->serialize($this->model, 'driver', true, []);

        $this->assertNull($result);
    }

    /**
     * Test serialize() with instance of class that is not AbstractTaskDriver
     */
    public function test_serialize_returns_class_name_even_for_non_task_driver_instances(): void
    {
        $instance = new \stdClass();

        $result = $this->cast->serialize($this->model, 'driver', $instance, []);

        $this->assertEquals(\stdClass::class, $result);
    }

    /**
     * Test serialize() with empty string
     */
    public function test_serialize_returns_null_when_value_is_empty_string(): void
    {
        $result = $this->cast->serialize($this->model, 'driver', '', []);

        $this->assertNull($result);
    }

    /**
     * Test serialize() with attribute that doesn't have the key
     */
    public function test_serialize_returns_null_when_value_is_null_and_key_not_in_attributes(): void
    {
        $attributes = ['other_key' => 'value'];

        $result = $this->cast->serialize($this->model, 'driver', null, $attributes);

        $this->assertNull($result);
    }

    /**
     * Test serialize() with attribute that is null
     */
    public function test_serialize_returns_null_when_value_is_null_and_attribute_is_null(): void
    {
        $attributes = ['driver' => null];

        $result = $this->cast->serialize($this->model, 'driver', null, $attributes);

        $this->assertNull($result);
    }

    // ==================== Integration Tests ====================

    /**
     * Integration test: set then get
     */
    public function test_integration_set_then_get(): void
    {
        $fqcn = DummyActionTask::class;

        // set
        $stored = $this->cast->set($this->model, 'driver', $fqcn, []);
        $this->assertEquals(DummyActionTask::class, $stored);

        // get
        $attributes = ['id' => 1, 'driver' => $stored];
        $retrieved = $this->cast->get($this->model, 'driver', $stored, $attributes);

        $this->assertInstanceOf(DummyActionTask::class, $retrieved);
    }

    /**
     * Integration test: set with instance then get
     */
    public function test_integration_set_instance_then_get(): void
    {
        $instance = new DummyActionTask();

        // set
        $stored = $this->cast->set($this->model, 'driver', $instance, []);
        $this->assertEquals(DummyActionTask::class, $stored);

        // get
        $attributes = ['id' => 1, 'driver' => $stored];
        $retrieved = $this->cast->get($this->model, 'driver', $stored, $attributes);

        $this->assertInstanceOf(DummyActionTask::class, $retrieved);
    }

    /**
     * Integration test: serialize after set
     */
    public function test_integration_set_then_serialize(): void
    {
        $fqcn = DummyActionTask::class;

        // set
        $stored = $this->cast->set($this->model, 'driver', $fqcn, []);

        // serialize
        $attributes = ['driver' => $stored];
        $serialized = $this->cast->serialize($this->model, 'driver', null, $attributes);

        $this->assertEquals(DummyActionTask::class, $serialized);
    }

    /**
     * Integration test: set, serialize, then get
     */
    public function test_integration_set_serialize_then_get(): void
    {
        $fqcn = DummyActionTask::class;

        // set
        $stored = $this->cast->set($this->model, 'driver', $fqcn, []);

        // serialize
        $attributes = ['driver' => $stored];
        $serialized = $this->cast->serialize($this->model, 'driver', null, $attributes);

        // get
        $attributes = ['id' => 1, 'driver' => $serialized];
        $retrieved = $this->cast->get($this->model, 'driver', $serialized, $attributes);

        $this->assertInstanceOf(DummyActionTask::class, $retrieved);
    }

    /**
     * Integration test: set with forward slash, serialize, and get
     */
    public function test_integration_set_with_forward_slash_serialize_then_get(): void
    {
        $fqcnWithForwardSlash = str_replace('\\', '/', DummyActionTask::class);

        // set
        $stored = $this->cast->set($this->model, 'driver', $fqcnWithForwardSlash, []);
        $this->assertEquals(DummyActionTask::class, $stored);

        // serialize
        $attributes = ['driver' => $stored];
        $serialized = $this->cast->serialize($this->model, 'driver', null, $attributes);
        $this->assertEquals(DummyActionTask::class, $serialized);

        // get
        $attributes = ['id' => 1, 'driver' => $serialized];
        $retrieved = $this->cast->get($this->model, 'driver', $serialized, $attributes);

        $this->assertInstanceOf(DummyActionTask::class, $retrieved);
    }

    /**
     * Integration test: set null, serialize, and get
     */
    public function test_integration_set_null_serialize_then_get(): void
    {
        // set null
        $stored = $this->cast->set($this->model, 'driver', null, []);
        $this->assertNull($stored);

        // serialize
        $attributes = ['driver' => $stored];
        $serialized = $this->cast->serialize($this->model, 'driver', null, $attributes);
        $this->assertNull($serialized);

        // get
        $attributes = ['id' => 1, 'driver' => $stored];
        $retrieved = $this->cast->get($this->model, 'driver', $stored, $attributes);

        $this->assertNull($retrieved);
    }
}
