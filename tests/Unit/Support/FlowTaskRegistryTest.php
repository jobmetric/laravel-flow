<?php

namespace JobMetric\Flow\Tests\Unit\Support;

use InvalidArgumentException;
use JobMetric\Flow\Contracts\AbstractActionTask;
use JobMetric\Flow\Contracts\AbstractRestrictionTask;
use JobMetric\Flow\Contracts\AbstractTaskDriver;
use JobMetric\Flow\Contracts\AbstractValidationTask;
use JobMetric\Flow\Models\FlowTask;
use JobMetric\Flow\Support\FlowTaskRegistry;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Flow\Tests\TestCase;
use Throwable;

/**
 * Comprehensive tests for FlowTaskRegistry
 *
 * These tests cover all functionality of the FlowTaskRegistry class
 * to ensure it correctly registers, retrieves, and manages flow task drivers.
 */
class FlowTaskRegistryTest extends TestCase
{
    protected FlowTaskRegistry $registry;
    protected static int $classCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new FlowTaskRegistry();
    }

    /**
     * Test that register() accepts an AbstractTaskDriver and returns self
     *
     * @throws Throwable
     */
    public function test_register_accepts_task_driver_and_returns_self(): void
    {
        $task = $this->createActionTask(Order::class);

        $result = $this->registry->register($task);

        $this->assertInstanceOf(FlowTaskRegistry::class, $result);
        $this->assertSame($this->registry, $result);
    }

    /**
     * Test that register() indexes task by subject, type, and class name
     *
     * @throws Throwable
     */
    public function test_register_indexes_task_by_subject_type_and_class(): void
    {
        $task = $this->createActionTask(Order::class);
        $this->registry->register($task);

        $all = $this->registry->all();

        $this->assertArrayHasKey(Order::class, $all);
        $this->assertArrayHasKey(FlowTask::TYPE_ACTION, $all[Order::class]);
        $this->assertArrayHasKey(get_class($task), $all[Order::class][FlowTask::TYPE_ACTION]);
        $this->assertSame($task, $all[Order::class][FlowTask::TYPE_ACTION][get_class($task)]);
    }

    /**
     * Test that register() throws exception when subject is empty string
     *
     * @throws Throwable
     */
    public function test_register_throws_exception_when_subject_is_empty_string(): void
    {
        $task = $this->createActionTask('');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Task subject must not be an empty string.');

        $this->registry->register($task);
    }

    /**
     * Test that register() throws exception when same task is registered twice
     *
     * @throws Throwable
     */
    public function test_register_throws_exception_when_same_task_registered_twice(): void
    {
        $task1 = $this->createActionTask(Order::class);

        $this->registry->register($task1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Task '" . get_class($task1) . "' is already registered for subject '" . Order::class . "' and type '" . FlowTask::TYPE_ACTION . "'.");

        // Try to register the same instance again
        $this->registry->register($task1);
    }

    /**
     * Test that register() allows different task classes for same subject and type
     *
     * @throws Throwable
     */
    public function test_register_allows_different_task_classes_for_same_subject_and_type(): void
    {
        // Create tasks with different types to ensure different classes
        $task1 = $this->createActionTask(Order::class);
        $task2 = $this->createValidationTask(Order::class);

        $this->registry->register($task1);
        $this->registry->register($task2);

        $all = $this->registry->all();

        $this->assertArrayHasKey(Order::class, $all);
        $this->assertArrayHasKey(FlowTask::TYPE_ACTION, $all[Order::class]);
        $this->assertArrayHasKey(FlowTask::TYPE_VALIDATION, $all[Order::class]);
        $this->assertArrayHasKey(get_class($task1), $all[Order::class][FlowTask::TYPE_ACTION]);
        $this->assertArrayHasKey(get_class($task2), $all[Order::class][FlowTask::TYPE_VALIDATION]);
    }

    /**
     * Test that register() allows tasks for different subjects
     *
     * @throws Throwable
     */
    public function test_register_allows_tasks_for_different_subjects(): void
    {
        $subject1 = Order::class;
        $subject2 = 'App\\Models\\Product';

        $task1 = $this->createActionTask($subject1);
        $task2 = $this->createValidationTask($subject2);

        $this->registry->register($task1);
        $this->registry->register($task2);

        $all = $this->registry->all();

        $this->assertArrayHasKey($subject1, $all);
        $this->assertArrayHasKey($subject2, $all);
        $this->assertArrayHasKey(get_class($task1), $all[$subject1][FlowTask::TYPE_ACTION]);
        $this->assertArrayHasKey(get_class($task2), $all[$subject2][FlowTask::TYPE_VALIDATION]);
    }

    /**
     * Test that register() allows different task types for same subject
     *
     * @throws Throwable
     */
    public function test_register_allows_different_task_types_for_same_subject(): void
    {
        $actionTask = $this->createActionTask(Order::class);
        $validationTask = $this->createValidationTask(Order::class);
        $restrictionTask = $this->createRestrictionTask(Order::class);

        $this->registry->register($actionTask);
        $this->registry->register($validationTask);
        $this->registry->register($restrictionTask);

        $all = $this->registry->all();

        $this->assertArrayHasKey(get_class($actionTask), $all[Order::class][FlowTask::TYPE_ACTION]);
        $this->assertArrayHasKey(get_class($validationTask), $all[Order::class][FlowTask::TYPE_VALIDATION]);
        $this->assertArrayHasKey(get_class($restrictionTask), $all[Order::class][FlowTask::TYPE_RESTRICTION]);
    }

    /**
     * Test that all() returns empty array when no tasks registered
     */
    public function test_all_returns_empty_array_when_no_tasks_registered(): void
    {
        $all = $this->registry->all();

        $this->assertIsArray($all);
        $this->assertEmpty($all);
    }

    /**
     * Test that all() returns all registered tasks grouped by subject, type, and class
     *
     * @throws Throwable
     */
    public function test_all_returns_all_registered_tasks_grouped_correctly(): void
    {
        $actionTask = $this->createActionTask(Order::class);
        $validationTask = $this->createValidationTask(Order::class);
        $restrictionTask = $this->createRestrictionTask(Order::class);

        $this->registry->register($actionTask);
        $this->registry->register($validationTask);
        $this->registry->register($restrictionTask);

        $all = $this->registry->all();

        $this->assertArrayHasKey(Order::class, $all);
        $this->assertArrayHasKey(FlowTask::TYPE_ACTION, $all[Order::class]);
        $this->assertArrayHasKey(FlowTask::TYPE_VALIDATION, $all[Order::class]);
        $this->assertArrayHasKey(FlowTask::TYPE_RESTRICTION, $all[Order::class]);
        $this->assertCount(1, $all[Order::class][FlowTask::TYPE_ACTION]);
        $this->assertCount(1, $all[Order::class][FlowTask::TYPE_VALIDATION]);
        $this->assertCount(1, $all[Order::class][FlowTask::TYPE_RESTRICTION]);
    }

    /**
     * Test that all() returns tasks for multiple subjects
     *
     * @throws Throwable
     */
    public function test_all_returns_tasks_for_multiple_subjects(): void
    {
        $subject1 = Order::class;
        $subject2 = 'App\\Models\\Product';

        $task1 = $this->createActionTask($subject1);
        $task2 = $this->createActionTask($subject2);

        $this->registry->register($task1);
        $this->registry->register($task2);

        $all = $this->registry->all();

        $this->assertArrayHasKey($subject1, $all);
        $this->assertArrayHasKey($subject2, $all);
        $this->assertArrayHasKey(get_class($task1), $all[$subject1][FlowTask::TYPE_ACTION]);
        $this->assertArrayHasKey(get_class($task2), $all[$subject2][FlowTask::TYPE_ACTION]);
    }

    /**
     * Test that forSubject() returns empty array when subject not found
     */
    public function test_for_subject_returns_empty_array_when_subject_not_found(): void
    {
        $result = $this->registry->forSubject('NonExistent\\Model');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that forSubject() returns all tasks for a specific subject
     *
     * @throws Throwable
     */
    public function test_for_subject_returns_all_tasks_for_specific_subject(): void
    {
        $actionTask = $this->createActionTask(Order::class);
        $validationTask = $this->createValidationTask(Order::class);
        $restrictionTask = $this->createRestrictionTask(Order::class);

        $this->registry->register($actionTask);
        $this->registry->register($validationTask);
        $this->registry->register($restrictionTask);

        $result = $this->registry->forSubject(Order::class);

        $this->assertIsArray($result);
        $this->assertArrayHasKey(FlowTask::TYPE_ACTION, $result);
        $this->assertArrayHasKey(FlowTask::TYPE_VALIDATION, $result);
        $this->assertArrayHasKey(FlowTask::TYPE_RESTRICTION, $result);
        $this->assertCount(1, $result[FlowTask::TYPE_ACTION]);
        $this->assertCount(1, $result[FlowTask::TYPE_VALIDATION]);
        $this->assertCount(1, $result[FlowTask::TYPE_RESTRICTION]);
    }

    /**
     * Test that forSubject() returns only tasks for the specified subject
     *
     * @throws Throwable
     */
    public function test_for_subject_returns_only_tasks_for_specified_subject(): void
    {
        $subject1 = Order::class;
        $subject2 = 'App\\Models\\Product';

        $task1 = $this->createActionTask($subject1);
        $task2 = $this->createActionTask($subject2);

        $this->registry->register($task1);
        $this->registry->register($task2);

        $result = $this->registry->forSubject($subject1);

        $this->assertArrayHasKey(FlowTask::TYPE_ACTION, $result);
        $this->assertArrayHasKey(get_class($task1), $result[FlowTask::TYPE_ACTION]);
        $this->assertArrayNotHasKey(get_class($task2), $result[FlowTask::TYPE_ACTION] ?? []);
    }

    /**
     * Test that forSubjectAndType() throws exception for invalid type
     *
     * @throws Throwable
     */
    public function test_for_subject_and_type_throws_exception_for_invalid_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid task type 'invalid_type'.");

        $this->registry->forSubjectAndType(Order::class, 'invalid_type');
    }

    /**
     * Test that forSubjectAndType() returns empty array when subject not found
     *
     * @throws Throwable
     */
    public function test_for_subject_and_type_returns_empty_array_when_subject_not_found(): void
    {
        $result = $this->registry->forSubjectAndType('NonExistent\\Model', FlowTask::TYPE_ACTION);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that forSubjectAndType() returns empty array when type not found for subject
     *
     * @throws Throwable
     */
    public function test_for_subject_and_type_returns_empty_array_when_type_not_found(): void
    {
        $actionTask = $this->createActionTask(Order::class);
        $this->registry->register($actionTask);

        $result = $this->registry->forSubjectAndType(Order::class, FlowTask::TYPE_VALIDATION);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that forSubjectAndType() returns all tasks for specific subject and type
     *
     * @throws Throwable
     */
    public function test_for_subject_and_type_returns_tasks_for_specific_subject_and_type(): void
    {
        $actionTask1 = $this->createActionTask(Order::class);
        $actionTask2 = $this->createActionTask(Order::class);
        $validationTask = $this->createValidationTask(Order::class);

        $this->registry->register($actionTask1);
        $this->registry->register($actionTask2);
        $this->registry->register($validationTask);

        $result = $this->registry->forSubjectAndType(Order::class, FlowTask::TYPE_ACTION);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey(get_class($actionTask1), $result);
        $this->assertArrayHasKey(get_class($actionTask2), $result);
        $this->assertArrayNotHasKey(get_class($validationTask), $result);
    }

    /**
     * Test that forSubjectAndType() works with all valid types
     *
     * @throws Throwable
     */
    public function test_for_subject_and_type_works_with_all_valid_types(): void
    {
        $actionTask = $this->createActionTask(Order::class);
        $validationTask = $this->createValidationTask(Order::class);
        $restrictionTask = $this->createRestrictionTask(Order::class);

        $this->registry->register($actionTask);
        $this->registry->register($validationTask);
        $this->registry->register($restrictionTask);

        $actionResult = $this->registry->forSubjectAndType(Order::class, FlowTask::TYPE_ACTION);
        $validationResult = $this->registry->forSubjectAndType(Order::class, FlowTask::TYPE_VALIDATION);
        $restrictionResult = $this->registry->forSubjectAndType(Order::class, FlowTask::TYPE_RESTRICTION);

        $this->assertCount(1, $actionResult);
        $this->assertCount(1, $validationResult);
        $this->assertCount(1, $restrictionResult);
        $this->assertArrayHasKey(get_class($actionTask), $actionResult);
        $this->assertArrayHasKey(get_class($validationTask), $validationResult);
        $this->assertArrayHasKey(get_class($restrictionTask), $restrictionResult);
    }

    /**
     * Test that has() throws exception for invalid type
     *
     * @throws Throwable
     */
    public function test_has_throws_exception_for_invalid_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid task type 'invalid_type'.");

        $this->registry->has(Order::class, 'invalid_type', 'Some\\Task\\Class');
    }

    /**
     * Test that has() returns false when task not registered
     *
     * @throws Throwable
     */
    public function test_has_returns_false_when_task_not_registered(): void
    {
        $result = $this->registry->has(Order::class, FlowTask::TYPE_ACTION, 'NonExistent\\Task\\Class');

        $this->assertFalse($result);
    }

    /**
     * Test that has() returns true when task is registered
     *
     * @throws Throwable
     */
    public function test_has_returns_true_when_task_is_registered(): void
    {
        $task = $this->createActionTask(Order::class);
        $this->registry->register($task);

        $result = $this->registry->has(Order::class, FlowTask::TYPE_ACTION, get_class($task));

        $this->assertTrue($result);
    }

    /**
     * Test that has() returns false for wrong subject
     *
     * @throws Throwable
     */
    public function test_has_returns_false_for_wrong_subject(): void
    {
        $task = $this->createActionTask(Order::class);
        $this->registry->register($task);

        $result = $this->registry->has('App\\Models\\Product', FlowTask::TYPE_ACTION, get_class($task));

        $this->assertFalse($result);
    }

    /**
     * Test that has() returns false for wrong type
     *
     * @throws Throwable
     */
    public function test_has_returns_false_for_wrong_type(): void
    {
        $task = $this->createActionTask(Order::class);
        $this->registry->register($task);

        $result = $this->registry->has(Order::class, FlowTask::TYPE_VALIDATION, get_class($task));

        $this->assertFalse($result);
    }

    /**
     * Test that has() works with all valid types
     *
     * @throws Throwable
     */
    public function test_has_works_with_all_valid_types(): void
    {
        $actionTask = $this->createActionTask(Order::class);
        $validationTask = $this->createValidationTask(Order::class);
        $restrictionTask = $this->createRestrictionTask(Order::class);

        $this->registry->register($actionTask);
        $this->registry->register($validationTask);
        $this->registry->register($restrictionTask);

        $this->assertTrue($this->registry->has(Order::class, FlowTask::TYPE_ACTION, get_class($actionTask)));
        $this->assertTrue($this->registry->has(Order::class, FlowTask::TYPE_VALIDATION, get_class($validationTask)));
        $this->assertTrue($this->registry->has(Order::class, FlowTask::TYPE_RESTRICTION, get_class($restrictionTask)));
    }

    /**
     * Test that hasClass() returns false when class not registered
     */
    public function test_has_class_returns_false_when_class_not_registered(): void
    {
        $result = $this->registry->hasClass('NonExistent\\Task\\Class');

        $this->assertFalse($result);
    }

    /**
     * Test that hasClass() returns true when class is registered
     *
     * @throws Throwable
     */
    public function test_has_class_returns_true_when_class_is_registered(): void
    {
        $task = $this->createActionTask(Order::class);
        $this->registry->register($task);

        $result = $this->registry->hasClass(get_class($task));

        $this->assertTrue($result);
    }

    /**
     * Test that hasClass() normalizes forward slashes to backslashes
     *
     * @throws Throwable
     */
    public function test_has_class_normalizes_forward_slashes_to_backslashes(): void
    {
        $task = $this->createActionTask(Order::class);
        $this->registry->register($task);

        $className = get_class($task);
        $normalized = str_replace('\\', '/', $className);

        $result = $this->registry->hasClass($normalized);

        $this->assertTrue($result);
    }

    /**
     * Test that hasClass() trims whitespace
     *
     * @throws Throwable
     */
    public function test_has_class_trims_whitespace(): void
    {
        $task = $this->createActionTask(Order::class);
        $this->registry->register($task);

        $className = ' ' . get_class($task) . ' ';

        $result = $this->registry->hasClass($className);

        $this->assertTrue($result);
    }

    /**
     * Test that hasClass() works with multiple subjects and types
     *
     * @throws Throwable
     */
    public function test_has_class_works_with_multiple_subjects_and_types(): void
    {
        $subject1 = Order::class;
        $subject2 = 'App\\Models\\Product';

        $task1 = $this->createActionTask($subject1);
        $task2 = $this->createActionTask($subject2, get_class($task1));
        $task3 = $this->createValidationTask($subject1, get_class($task1));

        $this->registry->register($task1);
        $this->registry->register($task2);
        $this->registry->register($task3);

        $result = $this->registry->hasClass(get_class($task1));

        $this->assertTrue($result);
    }

    /**
     * Test that get() returns null when class not found
     */
    public function test_get_returns_null_when_class_not_found(): void
    {
        $result = $this->registry->get('NonExistent\\Task\\Class');

        $this->assertNull($result);
    }

    /**
     * Test that get() returns task instance when class is registered
     *
     * @throws Throwable
     */
    public function test_get_returns_task_instance_when_class_is_registered(): void
    {
        $task = $this->createActionTask(Order::class);
        $this->registry->register($task);

        $result = $this->registry->get(get_class($task));

        $this->assertInstanceOf(AbstractTaskDriver::class, $result);
        $this->assertSame($task, $result);
    }

    /**
     * Test that get() normalizes forward slashes to backslashes
     *
     * @throws Throwable
     */
    public function test_get_normalizes_forward_slashes_to_backslashes(): void
    {
        $task = $this->createActionTask(Order::class);
        $this->registry->register($task);

        $className = str_replace('\\', '/', get_class($task));

        $result = $this->registry->get($className);

        $this->assertInstanceOf(AbstractTaskDriver::class, $result);
        $this->assertSame($task, $result);
    }

    /**
     * Test that get() trims whitespace
     *
     * @throws Throwable
     */
    public function test_get_trims_whitespace(): void
    {
        $task = $this->createActionTask(Order::class);
        $this->registry->register($task);

        $className = ' ' . get_class($task) . ' ';

        $result = $this->registry->get($className);

        $this->assertInstanceOf(AbstractTaskDriver::class, $result);
        $this->assertSame($task, $result);
    }

    /**
     * Test that get() returns task instance when class is registered
     *
     * @throws Throwable
     */
    public function test_get_returns_task_instance_when_registered(): void
    {
        $subject1 = Order::class;
        $subject2 = 'App\\Models\\Product';

        $task1 = $this->createActionTask($subject1);
        $task2 = $this->createValidationTask($subject2);

        $this->registry->register($task1);
        $this->registry->register($task2);

        $result1 = $this->registry->get(get_class($task1));
        $result2 = $this->registry->get(get_class($task2));

        $this->assertInstanceOf(AbstractTaskDriver::class, $result1);
        $this->assertSame($task1, $result1);
        $this->assertInstanceOf(AbstractTaskDriver::class, $result2);
        $this->assertSame($task2, $result2);
    }

    /**
     * Test that get() works with all task types
     *
     * @throws Throwable
     */
    public function test_get_works_with_all_task_types(): void
    {
        $actionTask = $this->createActionTask(Order::class);
        $validationTask = $this->createValidationTask(Order::class);
        $restrictionTask = $this->createRestrictionTask(Order::class);

        $this->registry->register($actionTask);
        $this->registry->register($validationTask);
        $this->registry->register($restrictionTask);

        $actionResult = $this->registry->get(get_class($actionTask));
        $validationResult = $this->registry->get(get_class($validationTask));
        $restrictionResult = $this->registry->get(get_class($restrictionTask));

        $this->assertSame($actionTask, $actionResult);
        $this->assertSame($validationTask, $validationResult);
        $this->assertSame($restrictionTask, $restrictionResult);
    }

    /**
     * Test that register() supports method chaining
     *
     * @throws Throwable
     */
    public function test_register_supports_method_chaining(): void
    {
        $task1 = $this->createActionTask(Order::class);
        $task2 = $this->createValidationTask(Order::class);
        $task3 = $this->createRestrictionTask(Order::class);

        $result = $this->registry->register($task1)->register($task2)->register($task3);

        $this->assertSame($this->registry, $result);
        $this->assertTrue($this->registry->has(Order::class, FlowTask::TYPE_ACTION, get_class($task1)));
        $this->assertTrue($this->registry->has(Order::class, FlowTask::TYPE_VALIDATION, get_class($task2)));
        $this->assertTrue($this->registry->has(Order::class, FlowTask::TYPE_RESTRICTION, get_class($task3)));
    }

    /**
     * Test that registry maintains separate instances for different registries
     *
     * @throws Throwable
     */
    public function test_registry_maintains_separate_instances(): void
    {
        $registry1 = new FlowTaskRegistry();
        $registry2 = new FlowTaskRegistry();

        $task1 = $this->createActionTask(Order::class);
        $task2 = $this->createActionTask(Order::class);

        $registry1->register($task1);
        $registry2->register($task2);

        // Each registry should have its own tasks
        $this->assertTrue($registry1->hasClass(get_class($task1)));
        $this->assertTrue($registry2->hasClass(get_class($task2)));

        // Tasks registered in one registry should not be in another
        $this->assertFalse($registry1->hasClass(get_class($task2)));
        $this->assertFalse($registry2->hasClass(get_class($task1)));
    }

    /**
     * Test that all() returns the same reference (not a copy)
     *
     * @throws Throwable
     */
    public function test_all_returns_same_reference(): void
    {
        $task = $this->createActionTask(Order::class);
        $this->registry->register($task);

        $all1 = $this->registry->all();
        $all2 = $this->registry->all();

        // all() returns the same reference
        $this->assertSame($all1, $all2);
        $this->assertEquals($all1, $all2);
    }

    /**
     * Test that forSubject() returns the same reference (not a copy)
     *
     * @throws Throwable
     */
    public function test_for_subject_returns_same_reference(): void
    {
        $task = $this->createActionTask(Order::class);
        $this->registry->register($task);

        $result1 = $this->registry->forSubject(Order::class);
        $result2 = $this->registry->forSubject(Order::class);

        // forSubject() returns the same reference when subject exists
        $this->assertSame($result1, $result2);
        $this->assertEquals($result1, $result2);
    }

    /**
     * Test that forSubjectAndType() returns the same reference (not a copy)
     *
     * @throws Throwable
     */
    public function test_for_subject_and_type_returns_same_reference(): void
    {
        $task = $this->createActionTask(Order::class);
        $this->registry->register($task);

        $result1 = $this->registry->forSubjectAndType(Order::class, FlowTask::TYPE_ACTION);
        $result2 = $this->registry->forSubjectAndType(Order::class, FlowTask::TYPE_ACTION);

        // forSubjectAndType() returns the same reference
        $this->assertSame($result1, $result2);
        $this->assertEquals($result1, $result2);
    }

    /**
     * Test that get() returns the same instance (reference)
     *
     * @throws Throwable
     */
    public function test_get_returns_same_instance_reference(): void
    {
        $task = $this->createActionTask(Order::class);
        $this->registry->register($task);

        $result1 = $this->registry->get(get_class($task));
        $result2 = $this->registry->get(get_class($task));

        $this->assertSame($result1, $result2);
        $this->assertSame($task, $result1);
    }

    /**
     * Test complex scenario with multiple subjects, types, and classes
     *
     * @throws Throwable
     */
    public function test_complex_scenario_with_multiple_subjects_types_and_classes(): void
    {
        $subject1 = Order::class;
        $subject2 = 'App\\Models\\Product';
        $subject3 = 'App\\Models\\User';

        $actionTask1 = $this->createActionTask($subject1);
        $validationTask1 = $this->createValidationTask($subject1);
        $restrictionTask1 = $this->createRestrictionTask($subject1);

        $actionTask2 = $this->createActionTask($subject2);
        $validationTask2 = $this->createValidationTask($subject2);

        $restrictionTask2 = $this->createRestrictionTask($subject3);

        $this->registry->register($actionTask1);
        $this->registry->register($validationTask1);
        $this->registry->register($restrictionTask1);
        $this->registry->register($actionTask2);
        $this->registry->register($validationTask2);
        $this->registry->register($restrictionTask2);

        $all = $this->registry->all();

        $this->assertCount(3, $all);
        $this->assertArrayHasKey($subject1, $all);
        $this->assertArrayHasKey($subject2, $all);
        $this->assertArrayHasKey($subject3, $all);

        $this->assertCount(1, $all[$subject1][FlowTask::TYPE_ACTION]);
        $this->assertCount(1, $all[$subject1][FlowTask::TYPE_VALIDATION]);
        $this->assertCount(1, $all[$subject1][FlowTask::TYPE_RESTRICTION]);

        $this->assertCount(1, $all[$subject2][FlowTask::TYPE_ACTION]);
        $this->assertCount(1, $all[$subject2][FlowTask::TYPE_VALIDATION]);

        $this->assertCount(1, $all[$subject3][FlowTask::TYPE_RESTRICTION]);

        $this->assertTrue($this->registry->has($subject1, FlowTask::TYPE_ACTION, get_class($actionTask1)));
        $this->assertTrue($this->registry->has($subject1, FlowTask::TYPE_VALIDATION, get_class($validationTask1)));
        $this->assertTrue($this->registry->has($subject1, FlowTask::TYPE_RESTRICTION, get_class($restrictionTask1)));

        $this->assertTrue($this->registry->hasClass(get_class($actionTask1)));
        $this->assertTrue($this->registry->hasClass(get_class($validationTask1)));
        $this->assertTrue($this->registry->hasClass(get_class($restrictionTask1)));

        $this->assertSame($actionTask1, $this->registry->get(get_class($actionTask1)));
        $this->assertSame($validationTask1, $this->registry->get(get_class($validationTask1)));
        $this->assertSame($restrictionTask1, $this->registry->get(get_class($restrictionTask1)));
    }

    /**
     * Create a concrete action task instance for testing
     *
     * @param string $subject
     *
     * @return AbstractActionTask
     */
    protected function createActionTask(string $subject): AbstractActionTask
    {
        $counter = ++self::$classCounter;
        $subjectValue = addslashes($subject);

        // Use eval to create a unique class each time
        $className = "TestActionTask{$counter}";
        $code = "
            class {$className} extends \\JobMetric\\Flow\\Contracts\\AbstractActionTask
            {
                private static string \$subjectValue = '{$subjectValue}';

                public static function subject(): string
                {
                    return self::\$subjectValue;
                }

                public static function definition(): \\JobMetric\\Flow\\Support\\FlowTaskDefinition
                {
                    return new \\JobMetric\\Flow\\Support\\FlowTaskDefinition(title: 'Test Action Task');
                }

                public function form(): \\JobMetric\\Form\\FormBuilder
                {
                    return new \\JobMetric\\Form\\FormBuilder();
                }

                protected function handle(\\JobMetric\\Flow\\Support\\FlowTaskContext \$context): void
                {
                    // Empty implementation for testing
                }
            }
        ";

        eval($code);

        return new $className();
    }

    /**
     * Create a concrete validation task instance for testing
     *
     * @param string $subject
     *
     * @return AbstractValidationTask
     */
    protected function createValidationTask(string $subject): AbstractValidationTask
    {
        $counter = ++self::$classCounter;
        $subjectValue = addslashes($subject);

        // Use eval to create a unique class each time
        $className = "TestValidationTask{$counter}";
        $code = "
            class {$className} extends \\JobMetric\\Flow\\Contracts\\AbstractValidationTask
            {
                private static string \$subjectValue = '{$subjectValue}';

                public static function subject(): string
                {
                    return self::\$subjectValue;
                }

                public static function definition(): \\JobMetric\\Flow\\Support\\FlowTaskDefinition
                {
                    return new \\JobMetric\\Flow\\Support\\FlowTaskDefinition(title: 'Test Validation Task');
                }

                public function form(): \\JobMetric\\Form\\FormBuilder
                {
                    return new \\JobMetric\\Form\\FormBuilder();
                }

                public function rules(\\JobMetric\\Flow\\Support\\FlowTaskContext \$context): array
                {
                    return [];
                }
            }
        ";

        eval($code);

        return new $className();
    }

    /**
     * Create a concrete restriction task instance for testing
     *
     * @param string $subject
     *
     * @return AbstractRestrictionTask
     */
    protected function createRestrictionTask(string $subject): AbstractRestrictionTask
    {
        $counter = ++self::$classCounter;
        $subjectValue = addslashes($subject);

        // Use eval to create a unique class each time
        $className = "TestRestrictionTask{$counter}";
        $code = "
            class {$className} extends \\JobMetric\\Flow\\Contracts\\AbstractRestrictionTask
            {
                private static string \$subjectValue = '{$subjectValue}';

                public static function subject(): string
                {
                    return self::\$subjectValue;
                }

                public static function definition(): \\JobMetric\\Flow\\Support\\FlowTaskDefinition
                {
                    return new \\JobMetric\\Flow\\Support\\FlowTaskDefinition(title: 'Test Restriction Task');
                }

                public function form(): \\JobMetric\\Form\\FormBuilder
                {
                    return new \\JobMetric\\Form\\FormBuilder();
                }

                public function restriction(\\JobMetric\\Flow\\Support\\FlowTaskContext \$context): \\JobMetric\\Flow\\Support\\RestrictionResult
                {
                    return \\JobMetric\\Flow\\Support\\RestrictionResult::allow();
                }
            }
        ";

        eval($code);

        return new $className();
    }
}
