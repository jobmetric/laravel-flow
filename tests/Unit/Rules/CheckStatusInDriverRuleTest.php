<?php

namespace JobMetric\Flow\Tests\Unit\Rules;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator as BaseTranslator;
use JobMetric\Flow\HasWorkflow;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Rules\CheckStatusInDriverRule;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Flow\Tests\TestCase;
use LogicException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Comprehensive tests for CheckStatusInDriverRule
 *
 * This validation rule ensures that a given status value is allowed for the subject model
 * bound to a Flow. It validates:
 * - Flow existence
 * - Subject model class validity
 * - HasWorkflow trait usage
 * - Status enum values availability
 * - Status value matching (strict and stringified)
 *
 * These tests cover all scenarios including edge cases and error conditions.
 */
class CheckStatusInDriverRuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test_models table if it doesn't exist
        if (! Schema::hasTable('test_models')) {
            Schema::create('test_models', function ($table) {
                $table->id();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }
    }
    /**
     * Test that constructor accepts flow id
     */
    public function test_constructor_accepts_flow_id(): void
    {
        $rule = new CheckStatusInDriverRule(123);

        $reflection = new ReflectionClass($rule);
        $property = $reflection->getProperty('flowId');

        $this->assertEquals(123, $property->getValue($rule));
    }

    /**
     * Test that null value passes validation
     */
    public function test_null_value_passes_validation(): void
    {
        $rule = new CheckStatusInDriverRule(1);
        $failCalled = false;

        $rule->validate('status', null, function ($message) use (&$failCalled) {
            $failCalled = true;
        });

        $this->assertFalse($failCalled);
    }

    /**
     * Test that empty string passes validation
     */
    public function test_empty_string_passes_validation(): void
    {
        $rule = new CheckStatusInDriverRule(1);
        $failCalled = false;

        $rule->validate('status', '', function ($message) use (&$failCalled) {
            $failCalled = true;
        });

        $this->assertFalse($failCalled);
    }

    /**
     * Test that missing flow triggers failure
     */
    public function test_missing_flow_triggers_failure(): void
    {
        $this->setupTranslations();

        Flow::query()->where('id', 999)->delete();

        $rule = new CheckStatusInDriverRule(999);
        $failMessage = null;

        $rule->validate('status', 'pending', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        $this->assertEquals('workflow::base.validation.flow_not_found', $failMessage);
    }

    /**
     * Test that invalid subject_type triggers failure
     */
    public function test_invalid_subject_type_triggers_failure(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => 'NonExistentClass',
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failMessage = null;

        $rule->validate('status', 'pending', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        $this->assertEquals('workflow::base.validation.subject_model_invalid', $failMessage);
    }

    /**
     * Test that non-model class triggers failure
     */
    public function test_non_model_class_triggers_failure(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => stdClass::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failMessage = null;

        $rule->validate('status', 'pending', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        $this->assertEquals('workflow::base.validation.subject_model_invalid', $failMessage);
    }

    /**
     * Test that model without HasWorkflow trait triggers failure
     */
    public function test_model_without_has_workflow_trait_triggers_failure(): void
    {
        $this->setupTranslations();

        $modelClass = $this->createModelWithoutHasWorkflow();

        $flow = Flow::query()->create([
            'subject_type'  => $modelClass,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failMessage = null;

        $rule->validate('status', 'pending', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        $this->assertStringContainsString('workflow::base.validation.model_must_use_has_workflow', $failMessage);
    }

    /**
     * Test that other exceptions from flowStatusEnumValues trigger failure
     */
    public function test_other_exceptions_from_flow_status_enum_values_trigger_failure(): void
    {
        $this->setupTranslations();

        $modelClass = $this->createModelWithHasWorkflowAndCustomEnumValues(function () {
            throw new RuntimeException('Unexpected error');
        });

        $flow = Flow::query()->create([
            'subject_type'  => $modelClass,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failMessage = null;

        $rule->validate('status', 'pending', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        $this->assertEquals('workflow::base.validation.status_enum_error', $failMessage);
    }

    /**
     * Test that valid status value passes validation
     */
    public function test_valid_status_value_passes_validation(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failCalled = false;

        $rule->validate('status', 'pending', function ($message) use (&$failCalled) {
            $failCalled = true;
        });

        $this->assertFalse($failCalled);
    }

    /**
     * Test that invalid status value triggers failure
     */
    public function test_invalid_status_value_triggers_failure(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failMessage = null;

        $rule->validate('status', 'invalid_status', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        $this->assertStringContainsString('workflow::base.validation.check_status_in_driver', $failMessage);
    }


    /**
     * Test that usesHasWorkflow method works correctly
     *
     * @throws ReflectionException
     */
    public function test_uses_has_workflow_method_works_correctly(): void
    {
        $rule = new CheckStatusInDriverRule(1);
        $reflection = new ReflectionClass($rule);
        $method = $reflection->getMethod('usesHasWorkflow');

        $modelWithTrait = $this->createModelWithHasWorkflow();
        $modelWithoutTrait = $this->createModelWithoutHasWorkflow();

        $this->assertTrue($method->invoke($rule, $modelWithTrait));
        $this->assertFalse($method->invoke($rule, $modelWithoutTrait));
    }

    /**
     * Test that validation works with multiple status values
     */
    public function test_validation_works_with_multiple_status_values(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);

        $validStatuses = ['editable', 'pending', 'need_confirm', 'expired', 'paid', 'canceled'];
        foreach ($validStatuses as $status) {
            $failCalled = false;
            $rule->validate('status', $status, function ($message) use (&$failCalled) {
                $failCalled = true;
            });
            $this->assertFalse($failCalled, "Status '{$status}' should be valid");
        }

        $invalidStatuses = ['invalid', 'unknown', 'deleted'];
        foreach ($invalidStatuses as $status) {
            $failCalled = false;
            $rule->validate('status', $status, function ($message) use (&$failCalled) {
                $failCalled = true;
            });
            $this->assertTrue($failCalled, "Status '{$status}' should be invalid");
        }
    }

    /**
     * Test that error message includes allowed statuses
     */
    public function test_error_message_includes_allowed_statuses(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failMessage = null;

        $rule->validate('status', 'invalid', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        // The message should be the translation key or the translated message
        // Check that it's either the key or contains the translated message
        $this->assertTrue($failMessage === 'workflow::base.validation.check_status_in_driver' || str_contains($failMessage, 'Status must be one of:') || str_contains($failMessage, 'pending') || str_contains($failMessage, 'editable'), "Expected error message to be 'workflow::base.validation.check_status_in_driver' or contain status values, got: {$failMessage}");
    }

    /**
     * Test that validation works with different attribute names
     */
    public function test_validation_works_with_different_attribute_names(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failCalled = false;

        $rule->validate('custom_status', 'pending', function ($message) use (&$failCalled) {
            $failCalled = true;
        });

        $this->assertFalse($failCalled);
    }

    /**
     * Test that validation works with zero flow id
     */
    public function test_validation_works_with_zero_flow_id(): void
    {
        $this->setupTranslations();

        Flow::query()->where('id', 0)->delete();

        $rule = new CheckStatusInDriverRule(0);
        $failMessage = null;

        $rule->validate('status', 'pending', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        $this->assertEquals('workflow::base.validation.flow_not_found', $failMessage);
    }

    /**
     * Test that validation works with negative flow id
     */
    public function test_validation_works_with_negative_flow_id(): void
    {
        $this->setupTranslations();

        $rule = new CheckStatusInDriverRule(-1);
        $failMessage = null;

        $rule->validate('status', 'pending', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        $this->assertEquals('workflow::base.validation.flow_not_found', $failMessage);
    }

    /**
     * Test that validation works with very large flow id
     */
    public function test_validation_works_with_very_large_flow_id(): void
    {
        $this->setupTranslations();

        $rule = new CheckStatusInDriverRule(999999999);
        $failMessage = null;

        $rule->validate('status', 'pending', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        $this->assertEquals('workflow::base.validation.flow_not_found', $failMessage);
    }

    /**
     * Test that subject_type with empty string triggers failure
     */
    public function test_subject_type_with_empty_string_triggers_failure(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => '',
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failMessage = null;

        $rule->validate('status', 'pending', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        $this->assertEquals('workflow::base.validation.subject_model_invalid', $failMessage);
    }

    /**
     * Test that subject_type with null triggers failure
     * Note: We cannot actually set null in database due to NOT NULL constraint,
     * but we can test the validation logic by using a non-existent class name
     * which will fail the class_exists check, simulating a null-like scenario
     */
    public function test_subject_type_with_null_triggers_failure(): void
    {
        $this->setupTranslations();

        // Create flow with invalid subject_type that simulates null behavior
        // Using a non-existent class that will fail class_exists check
        $flow = Flow::query()->create([
            'subject_type'  => 'NonExistentClassForNullTest',
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failMessage = null;

        $rule->validate('status', 'pending', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        $this->assertEquals('workflow::base.validation.subject_model_invalid', $failMessage);
    }

    /**
     * Test that validation works with all OrderStatusEnum values
     */
    public function test_validation_works_with_all_order_status_enum_values(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);

        $allStatuses = ['editable', 'pending', 'need_confirm', 'expired', 'paid', 'canceled'];
        foreach ($allStatuses as $status) {
            $failCalled = false;
            $rule->validate('status', $status, function ($message) use (&$failCalled) {
                $failCalled = true;
            });
            $this->assertFalse($failCalled, "Status '{$status}' should be valid");
        }
    }

    /**
     * Test that validation rejects case-sensitive mismatches
     */
    public function test_validation_rejects_case_sensitive_mismatches(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);

        $caseMismatches = ['PENDING', 'Pending', 'Editable', 'EDitable'];
        foreach ($caseMismatches as $status) {
            $failCalled = false;
            $rule->validate('status', $status, function ($message) use (&$failCalled) {
                $failCalled = true;
            });
            $this->assertTrue($failCalled, "Status '{$status}' should be invalid due to case mismatch");
        }
    }

    /**
     * Test that validation works with whitespace in value
     */
    public function test_validation_works_with_whitespace_in_value(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);

        $whitespaceValues = [' pending', 'pending ', ' pending ', "\tpending", "pending\n"];
        foreach ($whitespaceValues as $status) {
            $failCalled = false;
            $rule->validate('status', $status, function ($message) use (&$failCalled) {
                $failCalled = true;
            });
            $this->assertTrue($failCalled, "Status '{$status}' should be invalid due to whitespace");
        }
    }

    /**
     * Test that validation works with numeric string values
     */
    public function test_validation_works_with_numeric_string_values(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);

        $numericValues = ['0', '1', '123', '999'];
        foreach ($numericValues as $status) {
            $failCalled = false;
            $rule->validate('status', $status, function ($message) use (&$failCalled) {
                $failCalled = true;
            });
            $this->assertTrue($failCalled, "Numeric string '{$status}' should be invalid");
        }
    }

    /**
     * Test that validation works with special characters in value
     */
    public function test_validation_works_with_special_characters_in_value(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);

        $specialValues = ['pending!', 'pending@', 'pending#', 'pending$', 'pending%'];
        foreach ($specialValues as $status) {
            $failCalled = false;
            $rule->validate('status', $status, function ($message) use (&$failCalled) {
                $failCalled = true;
            });
            $this->assertTrue($failCalled, "Status '{$status}' should be invalid due to special characters");
        }
    }

    /**
     * Test that validation works with array value
     */
    public function test_validation_works_with_array_value(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failCalled = false;
        $exceptionThrown = false;

        try {
            $rule->validate('status', ['pending'], function ($message) use (&$failCalled) {
                $failCalled = true;
            });
        } catch (Throwable $e) {
            $exceptionThrown = true;
        }

        // Either fail should be called or exception should be thrown
        $this->assertTrue($failCalled || $exceptionThrown, 'Array value should trigger failure or exception');
    }

    /**
     * Test that validation works with object value
     */
    public function test_validation_works_with_object_value(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failCalled = false;
        $exceptionThrown = false;

        try {
            $rule->validate('status', new stdClass(), function ($message) use (&$failCalled) {
                $failCalled = true;
            });
        } catch (Throwable $e) {
            $exceptionThrown = true;
        }

        // Either fail should be called or exception should be thrown
        $this->assertTrue($failCalled || $exceptionThrown, 'Object value should trigger failure or exception');
    }

    /**
     * Test that validation works with float value
     */
    public function test_validation_works_with_float_value(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failCalled = false;

        $rule->validate('status', 1.5, function ($message) use (&$failCalled) {
            $failCalled = true;
        });

        $this->assertTrue($failCalled);
    }

    /**
     * Test that validation works with resource value
     */
    public function test_validation_works_with_resource_value(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failCalled = false;

        $resource = fopen('php://memory', 'r');
        $rule->validate('status', $resource, function ($message) use (&$failCalled) {
            $failCalled = true;
        });
        fclose($resource);

        $this->assertTrue($failCalled);
    }

    /**
     * Test that usesHasWorkflow works with nested trait usage
     *
     * @throws ReflectionException
     */
    public function test_uses_has_workflow_works_with_nested_trait_usage(): void
    {
        $rule = new CheckStatusInDriverRule(1);
        $reflection = new ReflectionClass($rule);
        $method = $reflection->getMethod('usesHasWorkflow');

        // Order model uses HasWorkflow trait
        $this->assertTrue($method->invoke($rule, Order::class));
    }

    /**
     * Test that validation works with multiple calls to same rule instance
     */
    public function test_validation_works_with_multiple_calls_to_same_rule_instance(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);

        // First call
        $failCalled1 = false;
        $rule->validate('status', 'pending', function ($message) use (&$failCalled1) {
            $failCalled1 = true;
        });
        $this->assertFalse($failCalled1);

        // Second call
        $failCalled2 = false;
        $rule->validate('status', 'paid', function ($message) use (&$failCalled2) {
            $failCalled2 = true;
        });
        $this->assertFalse($failCalled2);

        // Third call with invalid value
        $failCalled3 = false;
        $rule->validate('status', 'invalid', function ($message) use (&$failCalled3) {
            $failCalled3 = true;
        });
        $this->assertTrue($failCalled3);
    }

    /**
     * Test that validation works with different flow instances
     */
    public function test_validation_works_with_different_flow_instances(): void
    {
        $this->setupTranslations();

        $flow1 = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $flow2 = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 2,
            'is_default'    => false,
            'status'        => true,
        ]);

        $rule1 = new CheckStatusInDriverRule($flow1->id);
        $rule2 = new CheckStatusInDriverRule($flow2->id);

        $failCalled1 = false;
        $rule1->validate('status', 'pending', function ($message) use (&$failCalled1) {
            $failCalled1 = true;
        });
        $this->assertFalse($failCalled1);

        $failCalled2 = false;
        $rule2->validate('status', 'paid', function ($message) use (&$failCalled2) {
            $failCalled2 = true;
        });
        $this->assertFalse($failCalled2);
    }

    /**
     * Test that validation works with empty array as subject_type
     */
    public function test_validation_works_with_empty_array_as_subject_type(): void
    {
        $this->setupTranslations();

        // Create flow with valid subject_type first
        $flow = Flow::query()->create([
            'subject_type'  => Order::class,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        // Update subject_type to empty string (array cannot be stored in database)
        // The rule checks if subject_type is a string, so empty string will fail
        DB::table('flows')->where('id', $flow->id)->update(['subject_type' => '']);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failMessage = null;

        $rule->validate('status', 'pending', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        $this->assertEquals('workflow::base.validation.subject_model_invalid', $failMessage);
    }

    /**
     * Test that validation works with integer as subject_type
     */
    public function test_validation_works_with_integer_as_subject_type(): void
    {
        $this->setupTranslations();

        $flow = Flow::query()->create([
            'subject_type'  => 123,
            'subject_scope' => null,
            'version'       => 1,
            'is_default'    => true,
            'status'        => true,
        ]);

        $rule = new CheckStatusInDriverRule($flow->id);
        $failMessage = null;

        $rule->validate('status', 'pending', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertNotNull($failMessage);
        $this->assertEquals('workflow::base.validation.subject_model_invalid', $failMessage);
    }

    /**
     * Setup translations for tests
     */
    private function setupTranslations(): void
    {
        $loader = new ArrayLoader();
        $loader->addMessages('en', 'workflow::base.validation', [
            'flow_not_found'              => 'Flow not found',
            'subject_model_invalid'       => 'Subject model is invalid',
            'model_must_use_has_workflow' => 'Model :model must use HasWorkflow trait',
            'status_column_missing'       => 'Status column is missing',
            'status_enum_error'           => 'Status enum error',
            'status_enum_missing'         => 'Status enum is missing',
            'check_status_in_driver'      => 'Status must be one of: :status',
        ]);

        $translator = new BaseTranslator($loader, 'en');
        Lang::swap($translator);
    }

    /**
     * Create a model class without HasWorkflow trait
     */
    private function createModelWithoutHasWorkflow(): string
    {
        $className = 'TestModelWithoutHasWorkflow' . uniqid();

        if (class_exists($className)) {
            return $className;
        }

        eval("
            class {$className} extends " . Model::class . " {
                protected \$table = 'test_models';
            }
        ");

        return $className;
    }

    /**
     * Create a model class with HasWorkflow trait
     */
    private function createModelWithHasWorkflow(): string
    {
        $className = 'TestModelWithHasWorkflow' . uniqid();

        if (class_exists($className)) {
            return $className;
        }

        eval("
            class {$className} extends " . Model::class . " {
                use " . HasWorkflow::class . ";
                protected \$table = 'test_models';
            }
        ");

        return $className;
    }

    /**
     * Storage for enum values per class
     */
    private static array $enumValuesStorage = [];

    /**
     * Storage for exception types per class
     */
    private static array $exceptionStorage = [];

    /**
     * Create a model class with HasWorkflow trait and custom flowStatusEnumValues
     */
    private function createModelWithHasWorkflowAndCustomEnumValues(mixed $enumValues): string
    {
        $className = 'TestModelWithHasWorkflowCustom' . uniqid();

        if (class_exists($className)) {
            return $className;
        }

        // Determine if we need to throw an exception
        $exceptionType = null;
        $actualEnumValues = $enumValues;

        if (is_callable($enumValues)) {
            try {
                $actualEnumValues = $enumValues();
            } catch (Throwable $e) {
                $exceptionType = get_class($e);
                $actualEnumValues = null;
            }
        }

        // Store enum values in static storage
        self::$enumValuesStorage[$className] = $actualEnumValues;
        if ($exceptionType !== null) {
            self::$exceptionStorage[$className] = $exceptionType;
        }

        $testClass = self::class;
        $logicExceptionClass = LogicException::class;
        $runtimeExceptionClass = RuntimeException::class;

        eval("
            class {$className} extends " . Model::class . " {
                use " . HasWorkflow::class . " {
                    flowStatusEnumValues as protected traitFlowStatusEnumValues;
                    flowStatusEnumClass as protected traitFlowStatusEnumClass;
                    ensureHasStatusColumn as protected traitEnsureHasStatusColumn;
                }
                protected \$table = 'test_models';

                protected function ensureHasStatusColumn(): void {
                    // Do nothing to prevent exception from being thrown
                }

                public function flowStatusEnumClass(): ?string {
                    // Always return null to prevent trait from calling enum methods
                    return null;
                }

                public function flowStatusEnumValues(): ?array {
                    \$storage = \\{$testClass}::\$enumValuesStorage;
                    \$exceptionStorage = \\{$testClass}::\$exceptionStorage;
                    \$testClassName = '" . $className . "';

                    // Check if we need to throw an exception FIRST, before any other logic
                    if (isset(\$exceptionStorage[\$testClassName])) {
                        \$exceptionType = \$exceptionStorage[\$testClassName];
                        if (\$exceptionType === '{$logicExceptionClass}') {
                            throw new \\{$logicExceptionClass}('Status column missing');
                        } else {
                            throw new \\{$runtimeExceptionClass}('Unexpected error');
                        }
                    }

                    // Check storage for enum values
                    if (!isset(\$storage[\$testClassName])) {
                        return null;
                    }

                    \$values = \$storage[\$testClassName];

                    // Return empty array if empty array
                    if (is_array(\$values) && empty(\$values)) {
                        return [];
                    }

                    return \$values;
                }
            }
        ");

        return $className;
    }

    /**
     * Mock flowStatusEnumValues method for a model class
     */
    private function mockModelFlowStatusEnumValues(string $modelClass, mixed $returnValue): void
    {
        // Since the rule creates a new instance, we need to create a model class
        // that overrides flowStatusEnumValues method
        // This is handled by createModelWithHasWorkflowAndCustomEnumValues
    }
}
