<?php

namespace JobMetric\Flow\Tests\Unit\Contracts;

use JobMetric\Flow\Contracts\AbstractTaskDriver;
use JobMetric\Flow\Contracts\AbstractValidationTask;
use JobMetric\Flow\DTO\TransitionResult;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskDefinition;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Flow\Tests\TestCase;
use JobMetric\Form\FormBuilder;
use ReflectionClass;

/**
 * Comprehensive tests for AbstractValidationTask
 *
 * These tests cover all functionality of the AbstractValidationTask abstract class
 * to ensure it correctly handles validation rules, messages, and attributes.
 */
class AbstractValidationTaskTest extends TestCase
{
    /**
     * Test that AbstractValidationTask extends AbstractTaskDriver
     */
    public function test_abstract_validation_task_extends_abstract_task_driver(): void
    {
        $reflection = new ReflectionClass(AbstractValidationTask::class);

        $this->assertTrue($reflection->isAbstract());
        $this->assertTrue($reflection->isSubclassOf(AbstractTaskDriver::class));
    }

    /**
     * Test that rules() method is abstract and must be implemented
     */
    public function test_rules_method_is_abstract(): void
    {
        $reflection = new ReflectionClass(AbstractValidationTask::class);
        $method = $reflection->getMethod('rules');

        $this->assertTrue($method->isAbstract());
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test that rules() method has correct signature
     */
    public function test_rules_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(AbstractValidationTask::class);
        $method = $reflection->getMethod('rules');

        $this->assertEquals('rules', $method->getName());
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isAbstract());

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('context', $parameters[0]->getName());
        $this->assertEquals(FlowTaskContext::class, $parameters[0]->getType()->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * Test that messages() method is not abstract and has default implementation
     */
    public function test_messages_method_is_not_abstract(): void
    {
        $reflection = new ReflectionClass(AbstractValidationTask::class);
        $method = $reflection->getMethod('messages');

        $this->assertFalse($method->isAbstract());
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test that messages() method has correct signature
     */
    public function test_messages_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(AbstractValidationTask::class);
        $method = $reflection->getMethod('messages');

        $this->assertEquals('messages', $method->getName());
        $this->assertTrue($method->isPublic());
        $this->assertFalse($method->isAbstract());

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('context', $parameters[0]->getName());
        $this->assertEquals(FlowTaskContext::class, $parameters[0]->getType()->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * Test that messages() returns empty array by default
     */
    public function test_messages_returns_empty_array_by_default(): void
    {
        $task = $this->createConcreteTask();
        $context = $this->createMockContext();

        $messages = $task->messages($context);

        $this->assertIsArray($messages);
        $this->assertEmpty($messages);
    }

    /**
     * Test that messages() can be overridden
     */
    public function test_messages_can_be_overridden(): void
    {
        $task = $this->createConcreteTaskWithMessages();
        $context = $this->createMockContext();

        $messages = $task->messages($context);

        $this->assertIsArray($messages);
        $this->assertNotEmpty($messages);
        $this->assertArrayHasKey('field.required', $messages);
    }

    /**
     * Test that attributes() method is not abstract and has default implementation
     */
    public function test_attributes_method_is_not_abstract(): void
    {
        $reflection = new ReflectionClass(AbstractValidationTask::class);
        $method = $reflection->getMethod('attributes');

        $this->assertFalse($method->isAbstract());
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test that attributes() method has correct signature
     */
    public function test_attributes_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(AbstractValidationTask::class);
        $method = $reflection->getMethod('attributes');

        $this->assertEquals('attributes', $method->getName());
        $this->assertTrue($method->isPublic());
        $this->assertFalse($method->isAbstract());

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('context', $parameters[0]->getName());
        $this->assertEquals(FlowTaskContext::class, $parameters[0]->getType()->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * Test that attributes() returns empty array by default
     */
    public function test_attributes_returns_empty_array_by_default(): void
    {
        $task = $this->createConcreteTask();
        $context = $this->createMockContext();

        $attributes = $task->attributes($context);

        $this->assertIsArray($attributes);
        $this->assertEmpty($attributes);
    }

    /**
     * Test that attributes() can be overridden
     */
    public function test_attributes_can_be_overridden(): void
    {
        $task = $this->createConcreteTaskWithAttributes();
        $context = $this->createMockContext();

        $attributes = $task->attributes($context);

        $this->assertIsArray($attributes);
        $this->assertNotEmpty($attributes);
        $this->assertArrayHasKey('field', $attributes);
    }

    /**
     * Test that rules() receives correct context
     */
    public function test_rules_receives_correct_context(): void
    {
        $task = $this->createConcreteTask();
        $context = $this->createMockContext();

        $receivedContext = null;
        $task->setRulesCallback(function ($ctx) use (&$receivedContext) {
            $receivedContext = $ctx;

            return [];
        });

        $task->rules($context);

        $this->assertSame($context, $receivedContext);
    }

    /**
     * Test that messages() receives correct context
     */
    public function test_messages_receives_correct_context(): void
    {
        $task = $this->createConcreteTask();
        $context = $this->createMockContext();

        $receivedContext = null;
        $task->setMessagesCallback(function ($ctx) use (&$receivedContext) {
            $receivedContext = $ctx;

            return [];
        });

        $task->messages($context);

        $this->assertSame($context, $receivedContext);
    }

    /**
     * Test that attributes() receives correct context
     */
    public function test_attributes_receives_correct_context(): void
    {
        $task = $this->createConcreteTask();
        $context = $this->createMockContext();

        $receivedContext = null;
        $task->setAttributesCallback(function ($ctx) use (&$receivedContext) {
            $receivedContext = $ctx;

            return [];
        });

        $task->attributes($context);

        $this->assertSame($context, $receivedContext);
    }

    /**
     * Test that rules() can return different rules based on context
     */
    public function test_rules_can_return_different_rules_based_on_context(): void
    {
        $task = $this->createConcreteTaskWithConditionalRules();
        $context1 = $this->createMockContext();
        $context2 = $this->createMockContext();

        $rules1 = $task->rules($context1);
        $rules2 = $task->rules($context2);

        $this->assertIsArray($rules1);
        $this->assertIsArray($rules2);
    }

    /**
     * Test that rules() can use context config
     */
    public function test_rules_can_use_context_config(): void
    {
        $task = $this->createConcreteTaskWithConfigRules();
        $context = $this->createMockContext();
        $context->replaceConfig(['min_value' => 10]);

        $rules = $task->rules($context);

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('value', $rules);
    }

    /**
     * Test that rules() can use context subject
     */
    public function test_rules_can_use_context_subject(): void
    {
        $task = $this->createConcreteTaskWithSubjectRules();
        $context = $this->createMockContext();

        $rules = $task->rules($context);

        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }

    /**
     * Test that rules() can use context payload
     */
    public function test_rules_can_use_context_payload(): void
    {
        $task = $this->createConcreteTaskWithPayloadRules();
        $subject = Order::factory()->make();
        $result = new TransitionResult;
        $payload = ['action' => 'update'];
        $context = new FlowTaskContext($subject, $result, $payload);

        $rules = $task->rules($context);

        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }

    /**
     * Test that messages() can use context
     */
    public function test_messages_can_use_context(): void
    {
        $task = $this->createConcreteTaskWithContextMessages();
        $context = $this->createMockContext();

        $messages = $task->messages($context);

        $this->assertIsArray($messages);
        $this->assertNotEmpty($messages);
    }

    /**
     * Test that attributes() can use context
     */
    public function test_attributes_can_use_context(): void
    {
        $task = $this->createConcreteTaskWithContextAttributes();
        $context = $this->createMockContext();

        $attributes = $task->attributes($context);

        $this->assertIsArray($attributes);
        $this->assertNotEmpty($attributes);
    }

    /**
     * Test that rules() can return complex validation rules
     */
    public function test_rules_can_return_complex_validation_rules(): void
    {
        $task = $this->createConcreteTaskWithComplexRules();
        $context = $this->createMockContext();

        $rules = $task->rules($context);

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
    }

    /**
     * Test that multiple calls to rules() work independently
     */
    public function test_multiple_calls_to_rules_work_independently(): void
    {
        $task = $this->createConcreteTask();
        $context1 = $this->createMockContext();
        $context2 = $this->createMockContext();

        $callCount = 0;
        $task->setRulesCallback(function () use (&$callCount) {
            $callCount++;

            return [];
        });

        $task->rules($context1);
        $task->rules($context2);

        $this->assertEquals(2, $callCount);
    }

    /**
     * Test that rules() can be overridden in concrete classes
     */
    public function test_rules_can_be_overridden_in_concrete_classes(): void
    {
        $task1 = $this->createConcreteTask();
        $task2 = $this->createConcreteTaskWithComplexRules();
        $context = $this->createMockContext();

        $rules1 = $task1->rules($context);
        $rules2 = $task2->rules($context);

        $this->assertIsArray($rules1);
        $this->assertIsArray($rules2);
        $this->assertNotEquals($rules1, $rules2);
    }

    /**
     * Create a concrete task instance for testing
     */
    protected function createConcreteTask(): object
    {
        return new class extends AbstractValidationTask
        {
            protected $rulesCallback = null;
            protected $messagesCallback = null;
            protected $attributesCallback = null;

            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test Task');
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }

            public function rules(FlowTaskContext $context): array
            {
                if ($this->rulesCallback) {
                    return call_user_func($this->rulesCallback, $context);
                }

                return [];
            }

            public function messages(FlowTaskContext $context): array
            {
                if ($this->messagesCallback) {
                    return call_user_func($this->messagesCallback, $context);
                }

                return parent::messages($context);
            }

            public function attributes(FlowTaskContext $context): array
            {
                if ($this->attributesCallback) {
                    return call_user_func($this->attributesCallback, $context);
                }

                return parent::attributes($context);
            }

            public function setRulesCallback(callable $callback): void
            {
                $this->rulesCallback = $callback;
            }

            public function setMessagesCallback(callable $callback): void
            {
                $this->messagesCallback = $callback;
            }

            public function setAttributesCallback(callable $callback): void
            {
                $this->attributesCallback = $callback;
            }
        };
    }

    /**
     * Create a concrete task instance with messages
     */
    protected function createConcreteTaskWithMessages(): object
    {
        return new class extends AbstractValidationTask
        {
            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test Task');
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }

            public function rules(FlowTaskContext $context): array
            {
                return ['field' => 'required'];
            }

            public function messages(FlowTaskContext $context): array
            {
                return [
                    'field.required' => 'The field is required.',
                ];
            }
        };
    }

    /**
     * Create a concrete task instance with attributes
     */
    protected function createConcreteTaskWithAttributes(): object
    {
        return new class extends AbstractValidationTask
        {
            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test Task');
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }

            public function rules(FlowTaskContext $context): array
            {
                return ['field' => 'required'];
            }

            public function attributes(FlowTaskContext $context): array
            {
                return [
                    'field' => 'Custom Field Name',
                ];
            }
        };
    }

    /**
     * Create a concrete task instance with conditional rules
     */
    protected function createConcreteTaskWithConditionalRules(): object
    {
        return new class extends AbstractValidationTask
        {
            private $callCount = 0;

            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test Task');
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }

            public function rules(FlowTaskContext $context): array
            {
                $this->callCount++;

                if ($this->callCount === 1) {
                    return ['field1' => 'required'];
                }

                return ['field2' => 'required'];
            }
        };
    }

    /**
     * Create a concrete task instance with config rules
     */
    protected function createConcreteTaskWithConfigRules(): object
    {
        return new class extends AbstractValidationTask
        {
            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test Task');
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }

            public function rules(FlowTaskContext $context): array
            {
                $config = $context->config();
                $minValue = $config['min_value'] ?? 0;

                return [
                    'value' => "required|numeric|min:{$minValue}",
                ];
            }
        };
    }

    /**
     * Create a concrete task instance with subject rules
     */
    protected function createConcreteTaskWithSubjectRules(): object
    {
        return new class extends AbstractValidationTask
        {
            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test Task');
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }

            public function rules(FlowTaskContext $context): array
            {
                $subject = $context->subject();

                if ($subject instanceof Order) {
                    return ['order_field' => 'required'];
                }

                return [];
            }
        };
    }

    /**
     * Create a concrete task instance with payload rules
     */
    protected function createConcreteTaskWithPayloadRules(): object
    {
        return new class extends AbstractValidationTask
        {
            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test Task');
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }

            public function rules(FlowTaskContext $context): array
            {
                $payload = $context->payload();
                $action = $payload['action'] ?? null;

                if ($action === 'update') {
                    return ['data' => 'required'];
                }

                return [];
            }
        };
    }

    /**
     * Create a concrete task instance with context messages
     */
    protected function createConcreteTaskWithContextMessages(): object
    {
        return new class extends AbstractValidationTask
        {
            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test Task');
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }

            public function rules(FlowTaskContext $context): array
            {
                return ['field' => 'required'];
            }

            public function messages(FlowTaskContext $context): array
            {
                $subject = $context->subject();

                return [
                    'field.required' => 'The field is required for ' . get_class($subject),
                ];
            }
        };
    }

    /**
     * Create a concrete task instance with context attributes
     */
    protected function createConcreteTaskWithContextAttributes(): object
    {
        return new class extends AbstractValidationTask
        {
            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test Task');
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }

            public function rules(FlowTaskContext $context): array
            {
                return ['field' => 'required'];
            }

            public function attributes(FlowTaskContext $context): array
            {
                $subject = $context->subject();

                return [
                    'field' => 'Field for ' . get_class($subject),
                ];
            }
        };
    }

    /**
     * Create a concrete task instance with complex rules
     */
    protected function createConcreteTaskWithComplexRules(): object
    {
        return new class extends AbstractValidationTask
        {
            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test Task');
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }

            public function rules(FlowTaskContext $context): array
            {
                return [
                    'email'    => 'required|email|max:255',
                    'password' => 'required|string|min:8|confirmed',
                ];
            }
        };
    }

    /**
     * Create a mock FlowTaskContext
     */
    protected function createMockContext(): FlowTaskContext
    {
        $subject = Order::factory()->make();
        $result = new TransitionResult();

        return new FlowTaskContext($subject, $result);
    }
}
