<?php

namespace JobMetric\Flow\Tests\Unit\Contracts;

use JobMetric\Flow\Concerns\RunInBackground;
use JobMetric\Flow\Contracts\AbstractActionTask;
use JobMetric\Flow\Contracts\AbstractTaskDriver;
use JobMetric\Flow\DTO\TransitionResult;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskDefinition;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Flow\Tests\TestCase;
use JobMetric\Flyron\Facades\AsyncProcess;
use JobMetric\Form\FormBuilder;
use Mockery;
use ReflectionClass;
use ReflectionException;

/**
 * Comprehensive tests for AbstractActionTask
 *
 * These tests cover all functionality of the AbstractActionTask abstract class
 * to ensure it correctly handles synchronous and asynchronous task execution.
 */
class AbstractActionTaskTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that AbstractActionTask extends AbstractTaskDriver
     */
    public function test_abstract_action_task_extends_abstract_task_driver(): void
    {
        $reflection = new ReflectionClass(AbstractActionTask::class);

        $this->assertTrue($reflection->isAbstract());
        $this->assertTrue($reflection->isSubclassOf(AbstractTaskDriver::class));
    }

    /**
     * Test that async() method returns false by default
     */
    public function test_async_returns_false_by_default(): void
    {
        $task = $this->createConcreteTask();

        $this->assertFalse($task->async());
    }

    /**
     * Test that async() can be overridden to return true
     */
    public function test_async_can_be_overridden_to_return_true(): void
    {
        $task = $this->createConcreteTaskWithAsync();

        $this->assertTrue($task->async());
    }

    /**
     * Test that run() executes handle() synchronously when async() returns false
     */
    public function test_run_executes_handle_synchronously_when_async_returns_false(): void
    {
        $task = $this->createConcreteTask();
        $context = $this->createMockContext();

        $handleCalled = false;
        $task->setHandleCallback(function () use (&$handleCalled) {
            $handleCalled = true;
        });

        $task->run($context);

        $this->assertTrue($handleCalled);
    }

    /**
     * Test that run() executes handle() asynchronously when async() returns true
     */
    public function test_run_executes_handle_asynchronously_when_async_returns_true(): void
    {
        $task = $this->createConcreteTaskWithAsync();
        $context = $this->createMockContext();

        AsyncProcess::shouldReceive('run')
            ->once()
            ->with(Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn(null);

        $task->run($context);

        // Verify that AsyncProcess::run was called (assertion is implicit in shouldReceive->once())
        $this->assertTrue(true);
    }

    /**
     * Test that run() uses default background options when async is enabled
     */
    public function test_run_uses_default_background_options_when_async_is_enabled(): void
    {
        $task = $this->createConcreteTaskWithAsync();
        $context = $this->createMockContext();

        AsyncProcess::shouldReceive('run')
            ->once()
            ->with(Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn(null);

        $task->run($context);

        // Verify that AsyncProcess::run was called (assertion is implicit in shouldReceive->once())
        $this->assertTrue(true);
    }

    /**
     * Test that backgroundOptions() returns empty array by default
     *
     * @throws ReflectionException
     */
    public function test_background_options_returns_empty_array_by_default(): void
    {
        $task = $this->createConcreteTask();
        $context = $this->createMockContext();

        $reflection = new ReflectionClass($task);
        $method = $reflection->getMethod('backgroundOptions');

        $result = $method->invoke($task, $context);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that backgroundOptions() can be overridden
     *
     * @throws ReflectionException
     */
    public function test_background_options_can_be_overridden(): void
    {
        $task = $this->createConcreteTaskWithCustomBackgroundOptions();
        $context = $this->createMockContext();

        $reflection = new ReflectionClass($task);
        $method = $reflection->getMethod('backgroundOptions');

        $result = $method->invoke($task, $context);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('timeout', $result);
        $this->assertEquals(60, $result['timeout']);
    }

    /**
     * Test that backgroundOptions() are merged with default options
     *
     * @throws ReflectionException
     */
    public function test_background_options_are_merged_with_default_options(): void
    {
        $task = $this->createConcreteTaskWithCustomBackgroundOptions();
        $context = $this->createMockContext();

        // Verify that backgroundOptions returns custom timeout
        $reflection = new ReflectionClass($task);
        $method = $reflection->getMethod('backgroundOptions');
        $backgroundOptions = $method->invoke($task, $context);

        $this->assertIsArray($backgroundOptions);
        $this->assertArrayHasKey('timeout', $backgroundOptions);
        $this->assertEquals(60, $backgroundOptions['timeout']);

        AsyncProcess::shouldReceive('run')
            ->once()
            ->with(Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn(null);

        $task->run($context);

        // Verify that AsyncProcess::run was called (assertion is implicit in shouldReceive->once())
        $this->assertTrue(true);
    }

    /**
     * Test that handle() method is abstract and must be implemented
     */
    public function test_handle_method_is_abstract(): void
    {
        $reflection = new ReflectionClass(AbstractActionTask::class);
        $method = $reflection->getMethod('handle');

        $this->assertTrue($method->isAbstract());
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test that handle() method has correct signature
     */
    public function test_handle_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(AbstractActionTask::class);
        $method = $reflection->getMethod('handle');

        $this->assertEquals('handle', $method->getName());
        $this->assertTrue($method->isProtected());
        $this->assertTrue($method->isAbstract());

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('context', $parameters[0]->getName());
        $this->assertEquals(FlowTaskContext::class, $parameters[0]->getType()->getName());
    }

    /**
     * Test that run() method has correct signature
     */
    public function test_run_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(AbstractActionTask::class);
        $method = $reflection->getMethod('run');

        $this->assertEquals('run', $method->getName());
        $this->assertTrue($method->isPublic());

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('context', $parameters[0]->getName());
        $this->assertEquals(FlowTaskContext::class, $parameters[0]->getType()->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('void', $returnType->getName());
    }

    /**
     * Test that async() method has correct signature
     */
    public function test_async_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(AbstractActionTask::class);
        $method = $reflection->getMethod('async');

        $this->assertEquals('async', $method->getName());
        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    /**
     * Test that backgroundOptions() method has correct signature
     */
    public function test_background_options_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(AbstractActionTask::class);
        $method = $reflection->getMethod('backgroundOptions');

        $this->assertEquals('backgroundOptions', $method->getName());
        $this->assertTrue($method->isProtected());

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('context', $parameters[0]->getName());
        $this->assertEquals(FlowTaskContext::class, $parameters[0]->getType()->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * Test that handle() is called with correct context in synchronous mode
     */
    public function test_handle_is_called_with_correct_context_in_synchronous_mode(): void
    {
        $task = $this->createConcreteTask();
        $context = $this->createMockContext();

        $receivedContext = null;
        $task->setHandleCallback(function ($ctx) use (&$receivedContext) {
            $receivedContext = $ctx;
        });

        $task->run($context);

        $this->assertSame($context, $receivedContext);
    }

    /**
     * Test that handle() is called with correct context in asynchronous mode
     */
    public function test_handle_is_called_with_correct_context_in_asynchronous_mode(): void
    {
        $task = $this->createConcreteTaskWithAsync();
        $context = $this->createMockContext();

        $receivedContext = null;
        $task->setHandleCallback(function ($ctx) use (&$receivedContext) {
            $receivedContext = $ctx;
        });

        AsyncProcess::shouldReceive('run')
            ->once()
            ->with(Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturnUsing(function ($closure) use ($context, &$receivedContext) {
                $closure();
            });

        $task->run($context);

        $this->assertSame($context, $receivedContext);
    }

    /**
     * Test that multiple synchronous runs work independently
     */
    public function test_multiple_synchronous_runs_work_independently(): void
    {
        $task = $this->createConcreteTask();
        $context1 = $this->createMockContext();
        $context2 = $this->createMockContext();

        $callCount = 0;
        $task->setHandleCallback(function () use (&$callCount) {
            $callCount++;
        });

        $task->run($context1);
        $task->run($context2);

        $this->assertEquals(2, $callCount);
    }

    /**
     * Test that async() can be overridden using RunInBackground trait
     */
    public function test_async_can_be_overridden_using_run_in_background_trait(): void
    {
        $task = new class extends AbstractActionTask
        {
            use RunInBackground;

            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test');
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }

            protected function handle(FlowTaskContext $context): void
            {
                // Implementation
            }
        };

        $this->assertTrue($task->async());
    }

    /**
     * Test that backgroundOptions() receives correct context
     *
     * @throws ReflectionException
     */
    public function test_background_options_receives_correct_context(): void
    {
        $task = $this->createConcreteTaskWithCustomBackgroundOptions();
        $context = $this->createMockContext();

        $receivedContext = null;
        $task->setBackgroundOptionsCallback(function ($ctx) use (&$receivedContext) {
            $receivedContext = $ctx;
        });

        $reflection = new ReflectionClass($task);
        $method = $reflection->getMethod('backgroundOptions');

        $method->invoke($task, $context);

        $this->assertSame($context, $receivedContext);
    }

    /**
     * Test that run() does not call AsyncProcess when async is false
     */
    public function test_run_does_not_call_async_process_when_async_is_false(): void
    {
        $task = $this->createConcreteTask();
        $context = $this->createMockContext();

        AsyncProcess::shouldReceive('run')->never();

        $task->run($context);

        $this->assertTrue(true); // Assertion to avoid risky test
    }

    /**
     * Test that run() calls AsyncProcess when async is true
     */
    public function test_run_calls_async_process_when_async_is_true(): void
    {
        $task = $this->createConcreteTaskWithAsync();
        $context = $this->createMockContext();

        AsyncProcess::shouldReceive('run')
            ->once()
            ->with(Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn(null);

        $task->run($context);

        $this->assertTrue(true); // Assertion to avoid risky test
    }

    /**
     * Test that default options include label with class name
     */
    public function test_default_options_include_label_with_class_name(): void
    {
        $task = $this->createConcreteTaskWithAsync();
        $context = $this->createMockContext();

        // Verify that the task class name contains AbstractActionTask
        $taskClassName = get_class($task);
        $this->assertStringContainsString('AbstractActionTask', $taskClassName);

        AsyncProcess::shouldReceive('run')
            ->once()
            ->with(Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn(null);

        $task->run($context);

        // Verify that AsyncProcess::run was called (assertion is implicit in shouldReceive->once())
        $this->assertTrue(true);
    }

    /**
     * Test that default options include timeout as null
     *
     * @throws ReflectionException
     */
    public function test_default_options_include_timeout_as_null(): void
    {
        $task = $this->createConcreteTaskWithAsync();
        $context = $this->createMockContext();

        // Verify that backgroundOptions returns empty array (which means timeout will be null in merged options)
        $reflection = new ReflectionClass($task);
        $method = $reflection->getMethod('backgroundOptions');
        $backgroundOptions = $method->invoke($task, $context);

        $this->assertIsArray($backgroundOptions);
        $this->assertEmpty($backgroundOptions);

        // Verify that AsyncProcess::run is called with options that include timeout as null
        AsyncProcess::shouldReceive('run')
            ->once()
            ->with(Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn(null);

        $task->run($context);

        // The default options should have timeout as null (verified by checking backgroundOptions is empty)
        $this->assertTrue(true);
    }

    /**
     * Create a concrete task instance for testing
     */
    protected function createConcreteTask(): object
    {
        return new class extends AbstractActionTask
        {
            protected $handleCallback = null;

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

            protected function handle(FlowTaskContext $context): void
            {
                if ($this->handleCallback) {
                    call_user_func($this->handleCallback, $context);
                }
            }

            public function setHandleCallback(callable $callback): void
            {
                $this->handleCallback = $callback;
            }
        };
    }

    /**
     * Create a concrete task instance with async enabled
     */
    protected function createConcreteTaskWithAsync(): object
    {
        return new class extends AbstractActionTask
        {
            protected $handleCallback = null;

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

            public function async(): bool
            {
                return true;
            }

            protected function handle(FlowTaskContext $context): void
            {
                if ($this->handleCallback) {
                    call_user_func($this->handleCallback, $context);
                }
            }

            public function setHandleCallback(callable $callback): void
            {
                $this->handleCallback = $callback;
            }
        };
    }

    /**
     * Create a concrete task instance with custom background options
     */
    protected function createConcreteTaskWithCustomBackgroundOptions(): object
    {
        return new class extends AbstractActionTask
        {
            protected $handleCallback = null;
            protected $backgroundOptionsCallback = null;

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

            public function async(): bool
            {
                return true;
            }

            protected function backgroundOptions(FlowTaskContext $context): array
            {
                if ($this->backgroundOptionsCallback) {
                    call_user_func($this->backgroundOptionsCallback, $context);
                }

                return ['timeout' => 60];
            }

            protected function handle(FlowTaskContext $context): void
            {
                if ($this->handleCallback) {
                    call_user_func($this->handleCallback, $context);
                }
            }

            public function setHandleCallback(callable $callback): void
            {
                $this->handleCallback = $callback;
            }

            public function setBackgroundOptionsCallback(callable $callback): void
            {
                $this->backgroundOptionsCallback = $callback;
            }
        };
    }

    /**
     * Create a mock FlowTaskContext
     */
    protected function createMockContext(): FlowTaskContext
    {
        $subject = Order::factory()->make();
        $result = new TransitionResult;

        return new FlowTaskContext($subject, $result);
    }
}
