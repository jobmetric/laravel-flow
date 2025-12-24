<?php

namespace JobMetric\Flow\Tests\Unit\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use JobMetric\Flow\Contracts\AbstractRestrictionTask;
use JobMetric\Flow\Contracts\AbstractTaskDriver;
use JobMetric\Flow\DTO\TransitionResult;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskDefinition;
use JobMetric\Flow\Support\RestrictionResult;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Flow\Tests\TestCase;
use JobMetric\Form\FormBuilder;
use Mockery;
use ReflectionClass;

/**
 * Comprehensive tests for AbstractRestrictionTask
 *
 * These tests cover all functionality of the AbstractRestrictionTask abstract class
 * to ensure it correctly handles restriction evaluation.
 */
class AbstractRestrictionTaskTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    /**
     * Test that AbstractRestrictionTask extends AbstractTaskDriver
     */
    public function test_abstract_restriction_task_extends_abstract_task_driver(): void
    {
        $reflection = new ReflectionClass(AbstractRestrictionTask::class);

        $this->assertTrue($reflection->isAbstract());
        $this->assertTrue($reflection->isSubclassOf(AbstractTaskDriver::class));
    }

    /**
     * Test that restriction() method is abstract and must be implemented
     */
    public function test_restriction_method_is_abstract(): void
    {
        $reflection = new ReflectionClass(AbstractRestrictionTask::class);
        $method = $reflection->getMethod('restriction');

        $this->assertTrue($method->isAbstract());
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test that restriction() method has correct signature
     */
    public function test_restriction_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(AbstractRestrictionTask::class);
        $method = $reflection->getMethod('restriction');

        $this->assertEquals('restriction', $method->getName());
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isAbstract());

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('context', $parameters[0]->getName());
        $this->assertEquals(FlowTaskContext::class, $parameters[0]->getType()->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(RestrictionResult::class, $returnType->getName());
    }

    /**
     * Test that restriction() can return RestrictionResult::allow()
     */
    public function test_restriction_can_return_allow(): void
    {
        $task = $this->createConcreteTask();
        $context = $this->createMockContext();

        $result = $task->restriction($context);

        $this->assertInstanceOf(RestrictionResult::class, $result);
        $this->assertTrue($result->allowed());
    }

    /**
     * Test that restriction() can return RestrictionResult::deny()
     */
    public function test_restriction_can_return_deny(): void
    {
        $task = $this->createConcreteTaskWithDeny();
        $context = $this->createMockContext();

        $result = $task->restriction($context);

        $this->assertInstanceOf(RestrictionResult::class, $result);
        $this->assertFalse($result->allowed());
        $this->assertNotNull($result->code());
        $this->assertEquals('DENIED', $result->code());
    }

    /**
     * Test that restriction() receives correct context
     */
    public function test_restriction_receives_correct_context(): void
    {
        $task = $this->createConcreteTask();
        $context = $this->createMockContext();

        $receivedContext = null;
        $task->setRestrictionCallback(function ($ctx) use (&$receivedContext) {
            $receivedContext = $ctx;

            return RestrictionResult::allow();
        });

        $task->restriction($context);

        $this->assertSame($context, $receivedContext);
    }

    /**
     * Test that restriction() can return different results based on context
     */
    public function test_restriction_can_return_different_results_based_on_context(): void
    {
        $task = $this->createConcreteTaskWithConditionalRestriction();
        $context1 = $this->createMockContext();
        $context2 = $this->createMockContext();

        // First context should allow
        $result1 = $task->restriction($context1);
        $this->assertTrue($result1->allowed());

        // Second context should deny
        $result2 = $task->restriction($context2);
        $this->assertFalse($result2->allowed());
    }

    /**
     * Test that restriction() can return result with code and message
     */
    public function test_restriction_can_return_result_with_code_and_message(): void
    {
        $task = $this->createConcreteTaskWithDeny();
        $context = $this->createMockContext();

        $result = $task->restriction($context);

        $this->assertInstanceOf(RestrictionResult::class, $result);
        $this->assertFalse($result->allowed());
        $this->assertEquals('DENIED', $result->code());
        $this->assertEquals('Access denied', $result->message());
    }

    /**
     * Test that restriction() can return result without code and message
     */
    public function test_restriction_can_return_result_without_code_and_message(): void
    {
        $task = $this->createConcreteTask();
        $context = $this->createMockContext();

        $result = $task->restriction($context);

        $this->assertInstanceOf(RestrictionResult::class, $result);
        $this->assertTrue($result->allowed());
        $this->assertNull($result->code());
        $this->assertNull($result->message());
    }

    /**
     * Test that multiple restriction calls work independently
     */
    public function test_multiple_restriction_calls_work_independently(): void
    {
        $task = $this->createConcreteTask();
        $context1 = $this->createMockContext();
        $context2 = $this->createMockContext();

        $callCount = 0;
        $task->setRestrictionCallback(function () use (&$callCount) {
            $callCount++;

            return RestrictionResult::allow();
        });

        $task->restriction($context1);
        $task->restriction($context2);

        $this->assertEquals(2, $callCount);
    }

    /**
     * Test that restriction() can use context config
     */
    public function test_restriction_can_use_context_config(): void
    {
        $task = $this->createConcreteTaskWithConfigCheck();
        $context = $this->createMockContext();
        $context->replaceConfig(['allow' => true]);

        $result = $task->restriction($context);

        $this->assertTrue($result->allowed());
    }

    /**
     * Test that restriction() can use context subject
     */
    public function test_restriction_can_use_context_subject(): void
    {
        $task = $this->createConcreteTaskWithSubjectCheck();
        $context = $this->createMockContext();

        $result = $task->restriction($context);

        $this->assertInstanceOf(RestrictionResult::class, $result);
        $this->assertTrue($result->allowed());
    }

    /**
     * Test that restriction() can use context payload
     */
    public function test_restriction_can_use_context_payload(): void
    {
        $task = $this->createConcreteTaskWithPayloadCheck();
        $subject = Order::factory()->make();
        $result = new TransitionResult;
        $payload = ['action' => 'approve'];
        $context = new FlowTaskContext($subject, $result, $payload);

        $restrictionResult = $task->restriction($context);

        $this->assertTrue($restrictionResult->allowed());
    }

    /**
     * Test that restriction() can use context user
     */
    public function test_restriction_can_use_context_user(): void
    {
        $task = $this->createConcreteTaskWithUserCheck();
        $subject = Order::factory()->make();
        $result = new TransitionResult;
        $user = Mockery::mock(Authenticatable::class);
        $user->shouldReceive('getAuthIdentifier')->andReturn(1);
        $context = new FlowTaskContext($subject, $result, [], $user);

        $restrictionResult = $task->restriction($context);

        $this->assertInstanceOf(RestrictionResult::class, $restrictionResult);
    }

    /**
     * Test that restriction() can return different codes for different scenarios
     */
    public function test_restriction_can_return_different_codes_for_different_scenarios(): void
    {
        $task = $this->createConcreteTaskWithMultipleCodes();
        $context = $this->createMockContext();

        $result = $task->restriction($context);

        $this->assertInstanceOf(RestrictionResult::class, $result);
        $this->assertFalse($result->allowed());
        $this->assertNotNull($result->code());
    }

    /**
     * Test that restriction() can return different messages for different scenarios
     */
    public function test_restriction_can_return_different_messages_for_different_scenarios(): void
    {
        $task = $this->createConcreteTaskWithMultipleMessages();
        $context = $this->createMockContext();

        $result = $task->restriction($context);

        $this->assertInstanceOf(RestrictionResult::class, $result);
        $this->assertFalse($result->allowed());
        $this->assertNotNull($result->message());
    }

    /**
     * Test that restriction() result can be checked multiple times
     */
    public function test_restriction_result_can_be_checked_multiple_times(): void
    {
        $task = $this->createConcreteTask();
        $context = $this->createMockContext();

        $result = $task->restriction($context);

        // Check multiple times
        $this->assertTrue($result->allowed());
        $this->assertTrue($result->allowed());
        $this->assertTrue($result->allowed());
    }

    /**
     * Test that restriction() can be overridden in concrete classes
     */
    public function test_restriction_can_be_overridden_in_concrete_classes(): void
    {
        $task1 = $this->createConcreteTask();
        $task2 = $this->createConcreteTaskWithDeny();
        $context = $this->createMockContext();

        $result1 = $task1->restriction($context);
        $result2 = $task2->restriction($context);

        $this->assertTrue($result1->allowed());
        $this->assertFalse($result2->allowed());
    }

    /**
     * Create a concrete task instance for testing
     */
    protected function createConcreteTask(): object
    {
        return new class extends AbstractRestrictionTask
        {
            protected $restrictionCallback = null;

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

            public function restriction(FlowTaskContext $context): RestrictionResult
            {
                if ($this->restrictionCallback) {
                    return call_user_func($this->restrictionCallback, $context);
                }

                return RestrictionResult::allow();
            }

            public function setRestrictionCallback(callable $callback): void
            {
                $this->restrictionCallback = $callback;
            }
        };
    }

    /**
     * Create a concrete task instance that denies
     */
    protected function createConcreteTaskWithDeny(): object
    {
        return new class extends AbstractRestrictionTask
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

            public function restriction(FlowTaskContext $context): RestrictionResult
            {
                return RestrictionResult::deny('DENIED', 'Access denied');
            }
        };
    }

    /**
     * Create a concrete task instance with conditional restriction
     */
    protected function createConcreteTaskWithConditionalRestriction(): object
    {
        return new class extends AbstractRestrictionTask
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

            public function restriction(FlowTaskContext $context): RestrictionResult
            {
                $this->callCount++;

                if ($this->callCount === 1) {
                    return RestrictionResult::allow();
                }

                return RestrictionResult::deny('DENIED', 'Access denied');
            }
        };
    }

    /**
     * Create a concrete task instance that checks config
     */
    protected function createConcreteTaskWithConfigCheck(): object
    {
        return new class extends AbstractRestrictionTask
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

            public function restriction(FlowTaskContext $context): RestrictionResult
            {
                $config = $context->config();
                $allow = $config['allow'] ?? false;

                if ($allow) {
                    return RestrictionResult::allow();
                }

                return RestrictionResult::deny('NOT_ALLOWED', 'Not allowed by config');
            }
        };
    }

    /**
     * Create a concrete task instance that checks subject
     */
    protected function createConcreteTaskWithSubjectCheck(): object
    {
        return new class extends AbstractRestrictionTask
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

            public function restriction(FlowTaskContext $context): RestrictionResult
            {
                $subject = $context->subject();

                if ($subject instanceof Order) {
                    return RestrictionResult::allow();
                }

                return RestrictionResult::deny('INVALID_SUBJECT', 'Invalid subject');
            }
        };
    }

    /**
     * Create a concrete task instance that checks payload
     */
    protected function createConcreteTaskWithPayloadCheck(): object
    {
        return new class extends AbstractRestrictionTask
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

            public function restriction(FlowTaskContext $context): RestrictionResult
            {
                $payload = $context->payload();
                $action = $payload['action'] ?? null;

                if ($action === 'approve') {
                    return RestrictionResult::allow();
                }

                return RestrictionResult::deny('INVALID_ACTION', 'Invalid action');
            }
        };
    }

    /**
     * Create a concrete task instance that checks user
     */
    protected function createConcreteTaskWithUserCheck(): object
    {
        return new class extends AbstractRestrictionTask
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

            public function restriction(FlowTaskContext $context): RestrictionResult
            {
                $user = $context->user();

                if ($user !== null) {
                    return RestrictionResult::allow();
                }

                return RestrictionResult::deny('NO_USER', 'No user provided');
            }
        };
    }

    /**
     * Create a concrete task instance with multiple codes
     */
    protected function createConcreteTaskWithMultipleCodes(): object
    {
        return new class extends AbstractRestrictionTask
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

            public function restriction(FlowTaskContext $context): RestrictionResult
            {
                return RestrictionResult::deny('CUSTOM_ERROR_CODE', 'Custom error message');
            }
        };
    }

    /**
     * Create a concrete task instance with multiple messages
     */
    protected function createConcreteTaskWithMultipleMessages(): object
    {
        return new class extends AbstractRestrictionTask
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

            public function restriction(FlowTaskContext $context): RestrictionResult
            {
                return RestrictionResult::deny('ERROR', 'This is a detailed error message explaining why access was denied');
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
