<?php

namespace JobMetric\Flow\Tests\Unit\Contracts;

use Illuminate\Database\Eloquent\Model;
use JobMetric\Flow\Contracts\AbstractTaskDriver;
use JobMetric\Flow\Support\FlowTaskDefinition;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Flow\Tests\TestCase;
use JobMetric\Form\FormBuilder;
use ReflectionClass;
use ReflectionMethod;

/**
 * Comprehensive tests for AbstractTaskDriver
 *
 * These tests cover all functionality of the AbstractTaskDriver abstract class
 * to ensure it correctly defines the contract for task drivers.
 */
class AbstractTaskDriverTest extends TestCase
{
    /**
     * Test that AbstractTaskDriver is abstract
     */
    public function test_abstract_task_driver_is_abstract(): void
    {
        $reflection = new ReflectionClass(AbstractTaskDriver::class);

        $this->assertTrue($reflection->isAbstract());
    }

    /**
     * Test that subject() method is abstract and must be implemented
     */
    public function test_subject_method_is_abstract(): void
    {
        $reflection = new ReflectionClass(AbstractTaskDriver::class);
        $method = $reflection->getMethod('subject');

        $this->assertTrue($method->isAbstract());
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    /**
     * Test that subject() method has correct signature
     */
    public function test_subject_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(AbstractTaskDriver::class);
        $method = $reflection->getMethod('subject');

        $this->assertEquals('subject', $method->getName());
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isAbstract());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('string', $returnType->getName());
    }

    /**
     * Test that definition() method is abstract and must be implemented
     */
    public function test_definition_method_is_abstract(): void
    {
        $reflection = new ReflectionClass(AbstractTaskDriver::class);
        $method = $reflection->getMethod('definition');

        $this->assertTrue($method->isAbstract());
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    /**
     * Test that definition() method has correct signature
     */
    public function test_definition_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(AbstractTaskDriver::class);
        $method = $reflection->getMethod('definition');

        $this->assertEquals('definition', $method->getName());
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isAbstract());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(FlowTaskDefinition::class, $returnType->getName());
    }

    /**
     * Test that form() method is abstract and must be implemented
     */
    public function test_form_method_is_abstract(): void
    {
        $reflection = new ReflectionClass(AbstractTaskDriver::class);
        $method = $reflection->getMethod('form');

        $this->assertTrue($method->isAbstract());
        $this->assertTrue($method->isPublic());
        $this->assertFalse($method->isStatic());
    }

    /**
     * Test that form() method has correct signature
     */
    public function test_form_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(AbstractTaskDriver::class);
        $method = $reflection->getMethod('form');

        $this->assertEquals('form', $method->getName());
        $this->assertTrue($method->isPublic());
        $this->assertFalse($method->isStatic());
        $this->assertTrue($method->isAbstract());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(FormBuilder::class, $returnType->getName());
    }

    /**
     * Test that subject() can be called statically and returns string
     */
    public function test_subject_can_be_called_statically_and_returns_string(): void
    {
        $task = $this->createConcreteTask();

        $subject = $task::subject();

        $this->assertIsString($subject);
        $this->assertEquals(Order::class, $subject);
    }

    /**
     * Test that definition() can be called statically and returns FlowTaskDefinition
     */
    public function test_definition_can_be_called_statically_and_returns_flow_task_definition(): void
    {
        $task = $this->createConcreteTask();

        $definition = $task::definition();

        $this->assertInstanceOf(FlowTaskDefinition::class, $definition);
        $this->assertEquals('Test Task', $definition->title);
    }

    /**
     * Test that form() can be called on instance and returns FormBuilder
     */
    public function test_form_can_be_called_on_instance_and_returns_form_builder(): void
    {
        $task = $this->createConcreteTask();

        $form = $task->form();

        $this->assertInstanceOf(FormBuilder::class, $form);
    }

    /**
     * Test that subject() returns valid class name
     */
    public function test_subject_returns_valid_class_name(): void
    {
        $task = $this->createConcreteTask();

        $subject = $task::subject();

        $this->assertIsString($subject);
        $this->assertTrue(class_exists($subject), "Subject class '{$subject}' does not exist");
    }

    /**
     * Test that definition() returns definition with title
     */
    public function test_definition_returns_definition_with_title(): void
    {
        $task = $this->createConcreteTask();

        $definition = $task::definition();

        $this->assertInstanceOf(FlowTaskDefinition::class, $definition);
        $this->assertNotEmpty($definition->title);
        $this->assertIsString($definition->title);
    }

    /**
     * Test that definition() can return definition with description
     */
    public function test_definition_can_return_definition_with_description(): void
    {
        $task = $this->createConcreteTaskWithDescription();

        $definition = $task::definition();

        $this->assertInstanceOf(FlowTaskDefinition::class, $definition);
        $this->assertNotNull($definition->description);
        $this->assertIsString($definition->description);
    }

    /**
     * Test that definition() can return definition with icon
     */
    public function test_definition_can_return_definition_with_icon(): void
    {
        $task = $this->createConcreteTaskWithIcon();

        $definition = $task::definition();

        $this->assertInstanceOf(FlowTaskDefinition::class, $definition);
        $this->assertNotNull($definition->icon);
        $this->assertIsString($definition->icon);
    }

    /**
     * Test that definition() can return definition with tags
     */
    public function test_definition_can_return_definition_with_tags(): void
    {
        $task = $this->createConcreteTaskWithTags();

        $definition = $task::definition();

        $this->assertInstanceOf(FlowTaskDefinition::class, $definition);
        $this->assertNotNull($definition->tags);
        $this->assertIsArray($definition->tags);
    }

    /**
     * Test that form() returns new FormBuilder instance each time
     */
    public function test_form_returns_new_form_builder_instance_each_time(): void
    {
        $task = $this->createConcreteTask();

        $form1 = $task->form();
        $form2 = $task->form();

        $this->assertInstanceOf(FormBuilder::class, $form1);
        $this->assertInstanceOf(FormBuilder::class, $form2);
        $this->assertNotSame($form1, $form2);
    }

    /**
     * Test that subject() can return different model classes
     */
    public function test_subject_can_return_different_model_classes(): void
    {
        $task1 = $this->createConcreteTask();
        $task2 = $this->createConcreteTaskWithDifferentSubject();

        $subject1 = $task1::subject();
        $subject2 = $task2::subject();

        $this->assertNotEquals($subject1, $subject2);
        $this->assertTrue(class_exists($subject1));
        $this->assertTrue(class_exists($subject2));
    }

    /**
     * Test that definition() can return different definitions
     */
    public function test_definition_can_return_different_definitions(): void
    {
        $task1 = $this->createConcreteTask();
        $task2 = $this->createConcreteTaskWithDescription();

        $definition1 = $task1::definition();
        $definition2 = $task2::definition();

        $this->assertNotEquals($definition1->title, $definition2->title);
    }

    /**
     * Test that form() can return different form configurations
     */
    public function test_form_can_return_different_form_configurations(): void
    {
        $task1 = $this->createConcreteTask();
        $task2 = $this->createConcreteTaskWithCustomForm();

        $form1 = $task1->form();
        $form2 = $task2->form();

        $this->assertInstanceOf(FormBuilder::class, $form1);
        $this->assertInstanceOf(FormBuilder::class, $form2);
    }

    /**
     * Test that all abstract methods must be implemented
     */
    public function test_all_abstract_methods_must_be_implemented(): void
    {
        $reflection = new ReflectionClass(AbstractTaskDriver::class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_ABSTRACT);

        $this->assertCount(3, $methods);

        $methodNames = array_map(function ($method) {
            return $method->getName();
        }, $methods);

        $this->assertContains('subject', $methodNames);
        $this->assertContains('definition', $methodNames);
        $this->assertContains('form', $methodNames);
    }

    /**
     * Test that subject() is static method
     */
    public function test_subject_is_static_method(): void
    {
        $reflection = new ReflectionClass(AbstractTaskDriver::class);
        $method = $reflection->getMethod('subject');

        $this->assertTrue($method->isStatic());
    }

    /**
     * Test that definition() is static method
     */
    public function test_definition_is_static_method(): void
    {
        $reflection = new ReflectionClass(AbstractTaskDriver::class);
        $method = $reflection->getMethod('definition');

        $this->assertTrue($method->isStatic());
    }

    /**
     * Test that form() is instance method
     */
    public function test_form_is_instance_method(): void
    {
        $reflection = new ReflectionClass(AbstractTaskDriver::class);
        $method = $reflection->getMethod('form');

        $this->assertFalse($method->isStatic());
    }

    /**
     * Test that subject() can be called without instance
     */
    public function test_subject_can_be_called_without_instance(): void
    {
        $concreteTaskClass = get_class($this->createConcreteTask());

        $subject = $concreteTaskClass::subject();

        $this->assertIsString($subject);
        $this->assertEquals(Order::class, $subject);
    }

    /**
     * Test that definition() can be called without instance
     */
    public function test_definition_can_be_called_without_instance(): void
    {
        $concreteTaskClass = get_class($this->createConcreteTask());

        $definition = $concreteTaskClass::definition();

        $this->assertInstanceOf(FlowTaskDefinition::class, $definition);
    }

    /**
     * Test that form() requires instance
     */
    public function test_form_requires_instance(): void
    {
        $task = $this->createConcreteTask();

        $form = $task->form();

        $this->assertInstanceOf(FormBuilder::class, $form);
    }

    /**
     * Create a concrete task instance for testing
     */
    protected function createConcreteTask(): object
    {
        return new class extends AbstractTaskDriver
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
        };
    }

    /**
     * Create a concrete task instance with description
     */
    protected function createConcreteTaskWithDescription(): object
    {
        return new class extends AbstractTaskDriver
        {
            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test Task With Description', description: 'This is a test task with description');
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }
        };
    }

    /**
     * Create a concrete task instance with icon
     */
    protected function createConcreteTaskWithIcon(): object
    {
        return new class extends AbstractTaskDriver
        {
            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test Task With Icon', icon: 'icon-test');
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }
        };
    }

    /**
     * Create a concrete task instance with tags
     */
    protected function createConcreteTaskWithTags(): object
    {
        return new class extends AbstractTaskDriver
        {
            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test Task With Tags', tags: ['test', 'example']);
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }
        };
    }

    /**
     * Create a concrete task instance with different subject
     */
    protected function createConcreteTaskWithDifferentSubject(): object
    {
        return new class extends AbstractTaskDriver
        {
            public static function subject(): string
            {
                return Model::class;
            }

            public static function definition(): FlowTaskDefinition
            {
                return new FlowTaskDefinition(title: 'Test Task');
            }

            public function form(): FormBuilder
            {
                return new FormBuilder();
            }
        };
    }

    /**
     * Create a concrete task instance with custom form
     */
    protected function createConcreteTaskWithCustomForm(): object
    {
        return new class extends AbstractTaskDriver
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
                return (new FormBuilder())->tab(function ($tab) {
                    $tab->id('custom')->label('Custom');
                });
            }
        };
    }
}
