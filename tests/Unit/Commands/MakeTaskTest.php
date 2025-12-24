<?php

namespace JobMetric\Flow\Tests\Unit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use JobMetric\Flow\Commands\MakeTask;
use JobMetric\Flow\Support\FilesystemHasWorkflowInModelLocator;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Flow\Tests\TestCase;
use Mockery;
use stdClass;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Comprehensive tests for MakeTask command
 *
 * These tests cover all possible scenarios for the MakeTask artisan command
 * to ensure it correctly generates Flow Task classes.
 */
class MakeTaskTest extends TestCase
{
    protected Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();

        // Ensure Flows directory doesn't exist before each test
        $flowsPath = app_path('Flows');
        if ($this->filesystem->exists($flowsPath)) {
            $this->filesystem->deleteDirectory($flowsPath);
        }
    }

    protected function tearDown(): void
    {
        // Clean up Flows directory after each test
        $flowsPath = app_path('Flows');
        if ($this->filesystem->exists($flowsPath)) {
            $this->filesystem->deleteDirectory($flowsPath);
        }

        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that MakeTask extends Command
     */
    public function test_make_task_extends_command(): void
    {
        $command = new MakeTask();

        $this->assertInstanceOf(Command::class, $command);
    }

    /**
     * Test command signature
     */
    public function test_command_has_correct_signature(): void
    {
        $command = new MakeTask();

        $this->assertEquals('flow:make-task', $command->getName());
    }

    /**
     * Test command description
     */
    public function test_command_has_description(): void
    {
        $command = new MakeTask();

        $this->assertEquals('Create a new Flow Task class.', $command->getDescription());
    }

    /**
     * Test creating Action task with all arguments provided
     */
    public function test_creates_action_task_with_all_arguments(): void
    {
        $this->mockModelLocator([Order::class]);

        $command = $this->createCommand([
            'type'    => 'Action',
            'name'    => 'Test',
            'model'   => Order::class,
            'title'   => 'Test Title',
            '--force' => false,
        ]);

        $exitCode = $command->handle();

        $this->assertEquals(CommandAlias::SUCCESS, $exitCode);

        $expectedPath = app_path('Flows' . DIRECTORY_SEPARATOR . 'Order' . DIRECTORY_SEPARATOR . 'TestActionTask.php');
        $this->assertFileExists($expectedPath);

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('namespace App\\Flows\\Order;', $content);
        $this->assertStringContainsString('class TestActionTask extends AbstractActionTask', $content);
        $this->assertStringContainsString('return \\' . Order::class . '::class;', $content);
        $this->assertStringContainsString("title: 'Test Title'", $content);
    }

    /**
     * Test creating Restriction task with all arguments provided
     */
    public function test_creates_restriction_task_with_all_arguments(): void
    {
        $this->mockModelLocator([Order::class]);

        $command = $this->createCommand([
            'type'    => 'Restriction',
            'name'    => 'TestRestriction',
            'model'   => Order::class,
            'title'   => 'Restriction Title',
            '--force' => false,
        ]);

        $exitCode = $command->handle();

        $this->assertEquals(CommandAlias::SUCCESS, $exitCode);

        $expectedPath = app_path('Flows' . DIRECTORY_SEPARATOR . 'Order' . DIRECTORY_SEPARATOR . 'TestRestrictionRestrictionTask.php');
        $this->assertFileExists($expectedPath);

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('class TestRestrictionRestrictionTask extends AbstractRestrictionTask', $content);
        $this->assertStringContainsString("title: 'Restriction Title'", $content);
    }

    /**
     * Test creating Validation task with all arguments provided
     */
    public function test_creates_validation_task_with_all_arguments(): void
    {
        $this->mockModelLocator([Order::class]);

        $command = $this->createCommand([
            'type'    => 'Validation',
            'name'    => 'TestValidation',
            'model'   => Order::class,
            'title'   => 'Validation Title',
            '--force' => false,
        ]);

        $exitCode = $command->handle();

        $this->assertEquals(CommandAlias::SUCCESS, $exitCode);

        $expectedPath = app_path('Flows' . DIRECTORY_SEPARATOR . 'Order' . DIRECTORY_SEPARATOR . 'TestValidationValidationTask.php');
        $this->assertFileExists($expectedPath);

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('class TestValidationValidationTask extends AbstractValidationTask', $content);
        $this->assertStringContainsString("title: 'Validation Title'", $content);
    }

    /**
     * Test that invalid task type returns failure
     */
    public function test_invalid_task_type_returns_failure(): void
    {
        $command = $this->createCommand([
            'type'    => 'InvalidType',
            'name'    => 'TestTask',
            'model'   => Order::class,
            'title'   => 'Test Title',
            '--force' => false,
        ]);

        $exitCode = $command->handle();

        $this->assertEquals(CommandAlias::FAILURE, $exitCode);
    }

    /**
     * Test that model without HasWorkflow trait returns failure
     */
    public function test_model_without_has_workflow_trait_returns_failure(): void
    {
        $this->mockModelLocator([stdClass::class]);

        $command = $this->createCommand([
            'type'    => 'Action',
            'name'    => 'TestTask',
            'model'   => stdClass::class,
            'title'   => 'Test Title',
            '--force' => false,
        ]);

        $exitCode = $command->handle();

        $this->assertEquals(CommandAlias::FAILURE, $exitCode);
    }

    /**
     * Test that task name is converted to StudlyCase
     */
    public function test_task_name_is_converted_to_studly_case(): void
    {
        $this->mockModelLocator([Order::class]);

        $command = $this->createCommand([
            'type'    => 'Action',
            'name'    => 'test-task-name',
            'model'   => Order::class,
            'title'   => 'Test Title',
            '--force' => false,
        ]);

        $exitCode = $command->handle();

        $this->assertEquals(CommandAlias::SUCCESS, $exitCode);

        $expectedPath = app_path('Flows' . DIRECTORY_SEPARATOR . 'Order' . DIRECTORY_SEPARATOR . 'TestTaskNameActionTask.php');
        $this->assertFileExists($expectedPath);
    }

    /**
     * Test that directory is created if it doesn't exist
     */
    public function test_directory_is_created_if_not_exists(): void
    {
        $this->mockModelLocator([Order::class]);

        $flowsPath = app_path('Flows' . DIRECTORY_SEPARATOR . 'Order');
        $this->assertDirectoryDoesNotExist($flowsPath);

        $command = $this->createCommand();
        $command->handle();

        $this->assertDirectoryExists($flowsPath);
    }

    /**
     * Test that existing file without force option shows error
     */
    public function test_existing_file_without_force_shows_error(): void
    {
        $this->mockModelLocator([Order::class]);

        // Create the file first
        $flowsPath = app_path('Flows' . DIRECTORY_SEPARATOR . 'Order');
        $this->filesystem->makeDirectory($flowsPath, 0775, true);
        $filePath = $flowsPath . DIRECTORY_SEPARATOR . 'TestActionTask.php';
        $this->filesystem->put($filePath, 'existing content');

        // Create command with custom confirm mock that returns false
        $defaults = [
            'type'    => 'Action',
            'name'    => 'Test',
            'model'   => Order::class,
            'title'   => 'Test Title',
            '--force' => false,
        ];

        $command = Mockery::mock(MakeTask::class)->makePartial();
        $command->shouldAllowMockingProtectedMethods();

        // Mock argument and option methods
        $command->shouldReceive('argument')->andReturnUsing(function ($key) use ($defaults) {
            return $defaults[$key] ?? null;
        });

        $command->shouldReceive('option')->andReturnUsing(function ($key) use ($defaults) {
            if ($key === 'force' || $key === 'f') {
                return $defaults['--force'] ?? false;
            }

            return false;
        });

        // Mock interactive methods
        $command->shouldReceive('choice')->andReturn('Action');
        $command->shouldReceive('ask')->andReturn(null);
        // Mock confirm to return false (user doesn't want to overwrite)
        $command->shouldReceive('confirm')->with('Do you want to overwrite the existing file?')->andReturn(false);
        $command->shouldReceive('line')->andReturnSelf();
        $command->shouldReceive('info')->andReturnSelf();
        $command->shouldReceive('newLine')->andReturnSelf();
        $command->shouldReceive('message')->andReturnSelf();

        $command->setLaravel($this->app);

        $exitCode = $command->handle();

        $this->assertEquals(CommandAlias::FAILURE, $exitCode);
    }

    /**
     * Test that force option overwrites existing file
     */
    public function test_force_option_overwrites_existing_file(): void
    {
        $this->mockModelLocator([Order::class]);

        // Create the file first
        $flowsPath = app_path('Flows' . DIRECTORY_SEPARATOR . 'Order');
        $this->filesystem->makeDirectory($flowsPath, 0775, true);
        $filePath = $flowsPath . DIRECTORY_SEPARATOR . 'TestActionTask.php';
        $this->filesystem->put($filePath, 'existing content');

        $command = $this->createCommand(['--force' => true]);

        $exitCode = $command->handle();

        $this->assertEquals(CommandAlias::SUCCESS, $exitCode);

        $content = file_get_contents($filePath);
        $this->assertStringNotContainsString('existing content', $content);
        $this->assertStringContainsString('class TestActionTask extends AbstractActionTask', $content);
    }

    /**
     * Test that stub content is correctly replaced
     */
    public function test_stub_content_is_correctly_replaced(): void
    {
        $this->mockModelLocator([Order::class]);

        $command = $this->createCommand();
        $command->handle();

        $filePath = app_path('Flows' . DIRECTORY_SEPARATOR . 'Order' . DIRECTORY_SEPARATOR . 'TestActionTask.php');
        $content = file_get_contents($filePath);

        // Check that all placeholders are replaced
        $this->assertStringNotContainsString('{{namespace}}', $content);
        $this->assertStringNotContainsString('{{task}}', $content);
        $this->assertStringNotContainsString('{{model}}', $content);
        $this->assertStringNotContainsString('{{title}}', $content);

        // Check that correct values are inserted
        $this->assertStringContainsString('namespace App\\Flows\\Order;', $content);
        $this->assertStringContainsString('class TestActionTask', $content);
        $this->assertStringContainsString('return \\' . Order::class . '::class;', $content);
        $this->assertStringContainsString("title: 'Test Title'", $content);
    }

    /**
     * Test that action stub contains correct structure
     */
    public function test_action_stub_contains_correct_structure(): void
    {
        $this->mockModelLocator([Order::class]);

        $command = $this->createCommand();
        $command->handle();

        $filePath = app_path('Flows' . DIRECTORY_SEPARATOR . 'Order' . DIRECTORY_SEPARATOR . 'TestActionTask.php');
        $content = file_get_contents($filePath);

        // Check for required methods
        $this->assertStringContainsString('public static function subject(): string', $content);
        $this->assertStringContainsString('public static function definition(): FlowTaskDefinition', $content);
        $this->assertStringContainsString('public function form(): FormBuilder', $content);
        $this->assertStringContainsString('protected function handle(FlowTaskContext $context): void', $content);

        // Check for required imports
        $this->assertStringContainsString('use JobMetric\\Flow\\Contracts\\AbstractActionTask;', $content);
        $this->assertStringContainsString('use JobMetric\\Flow\\Support\\FlowTaskContext;', $content);
        $this->assertStringContainsString('use JobMetric\\Flow\\Support\\FlowTaskDefinition;', $content);
        $this->assertStringContainsString('use JobMetric\\Form\\FormBuilder;', $content);
    }

    /**
     * Test that restriction stub contains correct structure
     */
    public function test_restriction_stub_contains_correct_structure(): void
    {
        $this->mockModelLocator([Order::class]);

        $command = $this->createCommand([
            'type'    => 'Restriction',
            'name'    => 'TestRestriction',
            'model'   => Order::class,
            'title'   => 'Test Title',
            '--force' => false,
        ]);
        $command->handle();

        $filePath = app_path('Flows' . DIRECTORY_SEPARATOR . 'Order' . DIRECTORY_SEPARATOR . 'TestRestrictionRestrictionTask.php');
        $content = file_get_contents($filePath);

        // Check for required methods
        $this->assertStringContainsString('public function restriction(FlowTaskContext $context): RestrictionResult', $content);

        // Check for required imports
        $this->assertStringContainsString('use JobMetric\\Flow\\Contracts\\AbstractRestrictionTask;', $content);
        $this->assertStringContainsString('use JobMetric\\Flow\\Support\\RestrictionResult;', $content);
    }

    /**
     * Test that validation stub contains correct structure
     */
    public function test_validation_stub_contains_correct_structure(): void
    {
        $this->mockModelLocator([Order::class]);

        $command = $this->createCommand([
            'type'    => 'Validation',
            'name'    => 'TestValidation',
            'model'   => Order::class,
            'title'   => 'Test Title',
            '--force' => false,
        ]);
        $command->handle();

        $filePath = app_path('Flows' . DIRECTORY_SEPARATOR . 'Order' . DIRECTORY_SEPARATOR . 'TestValidationValidationTask.php');
        $content = file_get_contents($filePath);

        // Check for required methods
        $this->assertStringContainsString('public function rules(FlowTaskContext $context): array', $content);
        $this->assertStringContainsString('public function messages(FlowTaskContext $context): array', $content);
        $this->assertStringContainsString('public function attributes(FlowTaskContext $context): array', $content);

        // Check for required imports
        $this->assertStringContainsString('use JobMetric\\Flow\\Contracts\\AbstractValidationTask;', $content);
    }

    /**
     * Test that command handles model class basename correctly
     */
    public function test_command_handles_model_class_basename_correctly(): void
    {
        $this->mockModelLocator([Order::class]);

        $command = $this->createCommand();
        $command->handle();

        // Check that directory uses class basename (Order) not full class name
        $expectedPath = app_path('Flows' . DIRECTORY_SEPARATOR . 'Order');
        $this->assertDirectoryExists($expectedPath);
    }

    /**
     * Test that command creates file with correct naming convention
     */
    public function test_command_creates_file_with_correct_naming_convention(): void
    {
        $this->mockModelLocator([Order::class]);

        $command = $this->createCommand([
            'type'    => 'Action',
            'name'    => 'MyCustomTask',
            'model'   => Order::class,
            'title'   => 'Test Title',
            '--force' => false,
        ]);
        $command->handle();

        // File should be named: {Name}{Type}Task.php
        $expectedPath = app_path('Flows' . DIRECTORY_SEPARATOR . 'Order' . DIRECTORY_SEPARATOR . 'MyCustomTaskActionTask.php');
        $this->assertFileExists($expectedPath);
    }

    /**
     * Test that command handles case-insensitive task type
     */
    public function test_command_handles_case_insensitive_task_type(): void
    {
        $this->mockModelLocator([Order::class]);

        // Test lowercase
        $command = $this->createCommand([
            'type'    => 'action',
            'name'    => 'Test',
            'model'   => Order::class,
            'title'   => 'Test Title',
            '--force' => false,
        ]);

        $exitCode = $command->handle();

        // Should fail because type must be exact match
        $this->assertEquals(CommandAlias::FAILURE, $exitCode);
    }

    /**
     * Test that command validates task type is one of allowed values
     */
    public function test_command_validates_task_type_is_allowed(): void
    {
        $command = $this->createCommand([
            'type'    => 'NotAllowed',
            'name'    => 'Test',
            'model'   => Order::class,
            'title'   => 'Test Title',
            '--force' => false,
        ]);

        $exitCode = $command->handle();

        $this->assertEquals(CommandAlias::FAILURE, $exitCode);
    }

    /**
     * Create a mock command with specified arguments
     */
    protected function createCommand(array $arguments = []): MakeTask
    {
        $defaults = [
            'type'    => 'Action',
            'name'    => 'Test',
            'model'   => Order::class,
            'title'   => 'Test Title',
            '--force' => false,
        ];

        $args = array_merge($defaults, $arguments);

        $command = Mockery::mock(MakeTask::class)->makePartial();
        $command->shouldAllowMockingProtectedMethods();

        // Mock argument and option methods
        $command->shouldReceive('argument')->andReturnUsing(function ($key) use ($args) {
            return $args[$key] ?? null;
        });

        $command->shouldReceive('option')->andReturnUsing(function ($key) use ($args) {
            $optionKey = '--' . $key;
            if (isset($args[$optionKey])) {
                return $args[$optionKey];
            }
            if ($key === 'force' || $key === 'f') {
                return $args['--force'] ?? false;
            }

            return false;
        });

        // Mock interactive methods to return default values (not called when args provided)
        $command->shouldReceive('choice')->andReturn('Action');
        $command->shouldReceive('ask')->andReturn(null);
        // Mock confirm with any argument, can be overridden in specific tests
        $command->shouldReceive('confirm')->with(Mockery::any())->andReturn(true);
        $command->shouldReceive('line')->andReturnSelf();
        $command->shouldReceive('info')->andReturnSelf();
        $command->shouldReceive('newLine')->andReturnSelf();

        $command->setLaravel($this->app);

        return $command;
    }

    /**
     * Mock model locator
     */
    protected function mockModelLocator(array $models): void
    {
        // Mock the static method FilesystemHasWorkflowInModelLocator::all()
        // Use overload: prefix to mock static methods (works before class is loaded)
        // If class is already loaded, use alias: instead
        try {
            $locator = Mockery::mock('overload:' . FilesystemHasWorkflowInModelLocator::class);
            $locator->shouldReceive('all')->andReturn($models);
        } catch (\Exception $e) {
            // If overload fails, try alias (for already loaded classes)
            $locator = Mockery::mock('alias:' . FilesystemHasWorkflowInModelLocator::class);
            $locator->shouldReceive('all')->andReturn($models);
        }
    }
}

