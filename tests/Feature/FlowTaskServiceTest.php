<?php

namespace JobMetric\Flow\Tests\Feature;

use Illuminate\Validation\ValidationException;
use JobMetric\Flow\Contracts\AbstractTaskDriver;
use JobMetric\Flow\Models\Flow as FlowModel;
use JobMetric\Flow\Models\FlowState as FlowStateModel;
use JobMetric\Flow\Models\FlowTask as FlowTaskModel;
use JobMetric\Flow\Models\FlowTransition as FlowTransitionModel;
use JobMetric\Flow\Services\Flow as FlowService;
use JobMetric\Flow\Services\FlowState as FlowStateService;
use JobMetric\Flow\Services\FlowTransition as FlowTransitionService;
use JobMetric\Flow\Support\FlowTaskRegistry;
use JobMetric\Flow\Tests\Stubs\Enums\OrderStatusEnum;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Flow\Tests\Stubs\Models\User;
use JobMetric\Flow\Tests\Stubs\Tasks\DummyActionTask;
use JobMetric\Flow\Tests\Stubs\Tasks\DummyValidationTask;
use JobMetric\Flow\Tests\TestCase as BaseTestCase;
use Throwable;

class FlowTaskServiceTest extends BaseTestCase
{
    protected FlowTaskRegistry $registry;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Get the registry singleton and register our stub tasks
        // Note: FlowTaskRegistry is bound as 'FlowTaskRegistry' string in ServiceProvider
        $this->registry = app('FlowTaskRegistry');
        $this->registry->register(new DummyActionTask);
        $this->registry->register(new DummyValidationTask);
    }

    /**
     * Build a valid flow payload.
     *
     * @param string $subject
     * @param string|null $scope
     * @param int $version
     * @param bool $isDefault
     * @param bool $status
     * @param string|null $title
     *
     * @return array<string,mixed>
     */
    protected function makeFlowPayload(
        string $subject,
        ?string $scope,
        int $version = 1,
        bool $isDefault = true,
        bool $status = true,
        ?string $title = null
    ): array {
        $translation = [
            'en' => [
                'name'        => $title ?? "Test Flow v{$version}",
                'description' => "Desc v{$version}",
            ],
        ];

        return [
            'subject_type'       => $subject,
            'subject_scope'      => $scope,
            'subject_collection' => null,
            'version'            => $version,
            'is_default'         => $isDefault,
            'status'             => $status,
            'active_from'        => null,
            'active_to'          => null,
            'channel'            => 'web',
            'ordering'           => 0,
            'rollout_pct'        => null,
            'environment'        => 'test',
            'translation'        => $translation,
        ];
    }

    /**
     * Create a Flow with START and two states plus a transition.
     *
     * @param string $scope
     *
     * @return array{flow: FlowModel, start: FlowStateModel, a: FlowStateModel, b: FlowStateModel, transition:
     *                     FlowTransitionModel}
     * @throws Throwable
     */
    protected function makeFlowWithTransition(string $scope): array
    {
        $flowService = app(FlowService::class);
        $stateService = app(FlowStateService::class);
        $transitionService = app(FlowTransitionService::class);

        $user = User::factory()->setName('TestUser')->create();

        $created = $flowService->store($this->makeFlowPayload(Order::class, $scope ?: (string) $user->id));
        $this->assertTrue($created->ok);

        /** @var FlowModel $flow */
        $flow = FlowModel::query()->findOrFail($created->data->id);

        $start = $flowService->getStartState($flow->id);
        $this->assertNotNull($start, 'START state must exist');

        /** @var FlowStateModel $a */
        $a = $stateService->store([
            'flow_id'     => $flow->id,
            'translation' => [
                'en' => [
                    'name'        => 'StateA',
                    'description' => null,
                ],
            ],
            'status'      => OrderStatusEnum::PENDING(),
            'is_terminal' => false,
        ])->data->resource;

        /** @var FlowStateModel $b */
        $b = $stateService->store([
            'flow_id'     => $flow->id,
            'translation' => [
                'en' => [
                    'name'        => 'StateB',
                    'description' => null,
                ],
            ],
            'status'      => OrderStatusEnum::PENDING(),
            'is_terminal' => false,
        ])->data->resource;

        // Create a transition from START to StateA
        $transition = $transitionService->store([
            'flow_id'     => $flow->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $start->id,
            'to'          => $a->id,
            'slug'        => 'start-to-a',
        ])->data->resource;

        return compact('flow', 'start', 'a', 'b', 'transition');
    }

    /**
     * Test: store creates a flow task with valid payload.
     *
     * @throws Throwable
     */
    public function test_store_creates_flow_task_with_valid_payload(): void
    {
        $service = app('flow-task');
        $source = $this->makeFlowWithTransition('TK1');

        $res = $service->store([
            'flow_transition_id' => $source['transition']->id,
            'driver'             => DummyActionTask::class,
            'config'             => [
                'message' => 'Hello World',
                'retries' => 5,
            ],
            'ordering'           => 0,
            'status'             => true,
        ]);

        $this->assertTrue($res->ok);
        $this->assertEquals(201, $res->status);

        /** @var FlowTaskModel $task */
        $task = $res->data->resource;

        $this->assertEquals($source['transition']->id, $task->flow_transition_id);
        $this->assertEquals(DummyActionTask::class, is_object($task->driver) ? get_class($task->driver) : $task->driver);
        $this->assertTrue($task->status);
    }

    /**
     * Test: store fails with non-existent driver class.
     *
     * @throws Throwable
     */
    public function test_store_fails_with_non_existent_driver(): void
    {
        $service = app('flow-task');
        $source = $this->makeFlowWithTransition('TK2');

        $this->expectException(ValidationException::class);

        $service->store([
            'flow_transition_id' => $source['transition']->id,
            'driver'             => 'NonExistent\\FakeTask',
            'config'             => [],
            'status'             => true,
        ]);
    }

    /**
     * Test: store fails with driver not extending AbstractTaskDriver.
     *
     * @throws Throwable
     */
    public function test_store_fails_with_invalid_driver_inheritance(): void
    {
        $service = app('flow-task');
        $source = $this->makeFlowWithTransition('TK3');

        $this->expectException(ValidationException::class);

        $service->store([
            'flow_transition_id' => $source['transition']->id,
            'driver'             => User::class, // User is not a task driver
            'config'             => [],
            'status'             => true,
        ]);
    }

    /**
     * Test: store fails with unregistered driver.
     *
     * @throws Throwable
     */
    public function test_store_fails_with_unregistered_driver(): void
    {
        $service = app('flow-task');
        $source = $this->makeFlowWithTransition('TK4');

        // Create an anonymous task driver that's not registered
        $unregisteredDriver = new class extends AbstractTaskDriver
        {
            public static function subject(): string
            {
                return Order::class;
            }

            public static function definition(): \JobMetric\Flow\Support\FlowTaskDefinition
            {
                return new \JobMetric\Flow\Support\FlowTaskDefinition('Unregistered');
            }

            public function form(): \JobMetric\Form\FormBuilder
            {
                return new \JobMetric\Form\FormBuilder();
            }
        };

        $this->expectException(ValidationException::class);

        $service->store([
            'flow_transition_id' => $source['transition']->id,
            'driver'             => get_class($unregisteredDriver),
            'config'             => [],
            'status'             => true,
        ]);
    }

    /**
     * Test: store fails with invalid transition_id.
     *
     * @throws Throwable
     */
    public function test_store_fails_with_invalid_transition_id(): void
    {
        $service = app('flow-task');

        $this->expectException(ValidationException::class);

        $service->store([
            'flow_transition_id' => 999999,
            'driver'             => DummyActionTask::class,
            'config'             => [],
            'status'             => true,
        ]);
    }

    /**
     * Test: store validates config fields based on driver's form.
     *
     * @throws Throwable
     */
    public function test_store_validates_config_from_driver_form(): void
    {
        $service = app('flow-task');
        $source = $this->makeFlowWithTransition('TK5');

        $this->expectException(ValidationException::class);

        // DummyActionTask requires 'message' field
        $service->store([
            'flow_transition_id' => $source['transition']->id,
            'driver'             => DummyActionTask::class,
            'config'             => [
                // missing required 'message' field
                'retries' => 5,
            ],
            'status'             => true,
        ]);
    }

    /**
     * Test: update changes task fields.
     *
     * @throws Throwable
     */
    public function test_update_changes_task_fields(): void
    {
        $service = app('flow-task');
        $source = $this->makeFlowWithTransition('TK6');

        $createRes = $service->store([
            'flow_transition_id' => $source['transition']->id,
            'driver'             => DummyActionTask::class,
            'config'             => [
                'message' => 'Hello World',
                'retries' => 3,
            ],
            'status'             => true,
        ]);

        $this->assertTrue($createRes->ok);

        /** @var FlowTaskModel $task */
        $task = $createRes->data->resource;

        $updateRes = $service->update($task->id, [
            'config' => [
                'message' => 'Updated Message',
                'retries' => 7,
            ],
            'status' => false,
        ]);

        $this->assertTrue($updateRes->ok);

        $task->refresh();

        $this->assertFalse($task->status);
        $this->assertEquals('Updated Message', $task->config['message'] ?? null);
    }

    /**
     * Test: update can change driver.
     *
     * @throws Throwable
     */
    public function test_update_can_change_driver(): void
    {
        $service = app('flow-task');
        $source = $this->makeFlowWithTransition('TK7');

        $createRes = $service->store([
            'flow_transition_id' => $source['transition']->id,
            'driver'             => DummyActionTask::class,
            'config'             => [
                'message' => 'Hello',
                'retries' => 3,
            ],
            'status'             => true,
        ]);

        $this->assertTrue($createRes->ok);

        /** @var FlowTaskModel $task */
        $task = $createRes->data->resource;

        $updateRes = $service->update($task->id, [
            'driver' => DummyValidationTask::class,
            'config' => [
                'min_amount' => 100,
            ],
        ]);

        $this->assertTrue($updateRes->ok);

        $task->refresh();

        $driverClass = is_object($task->driver) ? get_class($task->driver) : $task->driver;
        $this->assertEquals(DummyValidationTask::class, $driverClass);
    }

    /**
     * Test: delete removes task.
     *
     * @throws Throwable
     */
    public function test_delete_removes_task(): void
    {
        $service = app('flow-task');
        $source = $this->makeFlowWithTransition('TK8');

        $createRes = $service->store([
            'flow_transition_id' => $source['transition']->id,
            'driver'             => DummyActionTask::class,
            'config'             => [
                'message' => 'To be deleted',
            ],
            'status'             => true,
        ]);

        $this->assertTrue($createRes->ok);

        /** @var FlowTaskModel $task */
        $task = $createRes->data->resource;
        $taskId = $task->id;

        $deleteRes = $service->destroy($taskId);
        $this->assertTrue($deleteRes->ok);

        $this->assertNull(FlowTaskModel::query()->find($taskId));
    }

    /**
     * Test: resolveDriver returns registered driver instance.
     *
     * @throws Throwable
     */
    public function test_resolve_driver_returns_registered_driver(): void
    {
        $service = app('flow-task');

        $driver = $service->resolveDriver(DummyActionTask::class);

        $this->assertNotNull($driver);
        $this->assertInstanceOf(DummyActionTask::class, $driver);
    }

    /**
     * Test: resolveDriver returns null for unregistered driver.
     *
     * @throws Throwable
     */
    public function test_resolve_driver_returns_null_for_unregistered(): void
    {
        $service = app('flow-task');

        $driver = $service->resolveDriver('NonExistent\\FakeDriver');

        $this->assertNull($driver);
    }

    /**
     * Test: drivers returns registered tasks grouped by subject.
     *
     * @throws Throwable
     */
    public function test_drivers_returns_registered_tasks_grouped_by_subject(): void
    {
        $service = app('flow-task');

        $drivers = $service->drivers();

        $this->assertIsArray($drivers);
        $this->assertNotEmpty($drivers);

        // Find our Order subject group
        $orderGroup = collect($drivers)->firstWhere('subject', Order::class);
        $this->assertNotNull($orderGroup);
        $this->assertArrayHasKey('tasks', $orderGroup);
        $this->assertNotEmpty($orderGroup['tasks']);

        // Check that our dummy tasks are in there
        $taskClasses = collect($orderGroup['tasks'])->pluck('class')->all();
        $this->assertContains(DummyActionTask::class, $taskClasses);
        $this->assertContains(DummyValidationTask::class, $taskClasses);
    }

    /**
     * Test: drivers can filter by type.
     *
     * @throws Throwable
     */
    public function test_drivers_can_filter_by_type(): void
    {
        $service = app('flow-task');

        // Filter only action tasks
        $drivers = $service->drivers('', 'action');

        $this->assertIsArray($drivers);

        // Find our Order subject group
        $orderGroup = collect($drivers)->firstWhere('subject', Order::class);
        $this->assertNotNull($orderGroup);

        // All tasks should be action type
        foreach ($orderGroup['tasks'] as $task) {
            $this->assertEquals('action', $task['type']);
        }
    }

    /**
     * Test: drivers can filter by subject.
     *
     * @throws Throwable
     */
    public function test_drivers_can_filter_by_subject(): void
    {
        $service = app('flow-task');

        $drivers = $service->drivers(Order::class);

        $this->assertIsArray($drivers);
        $this->assertCount(1, $drivers);
        $this->assertEquals(Order::class, $drivers[0]['subject']);
    }

    /**
     * Test: details returns task information.
     *
     * @throws Throwable
     */
    public function test_details_returns_task_information(): void
    {
        $service = app('flow-task');

        $details = $service->details(Order::class, DummyActionTask::class);

        $this->assertIsArray($details);
        $this->assertNotEmpty($details);
        $this->assertArrayHasKey('key', $details);
        $this->assertArrayHasKey('title', $details);
        $this->assertArrayHasKey('type', $details);
        $this->assertArrayHasKey('class', $details);
        $this->assertEquals(DummyActionTask::class, $details['class']);
        $this->assertEquals('action', $details['type']);
    }

    /**
     * Test: details returns empty array for non-existent task.
     *
     * @throws Throwable
     */
    public function test_details_returns_empty_for_non_existent_task(): void
    {
        $service = app('flow-task');

        $details = $service->details(Order::class, 'NonExistentTask');

        $this->assertIsArray($details);
        $this->assertEmpty($details);
    }

    /**
     * Test: auto-ordering assigns incremental values.
     *
     * @throws Throwable
     */
    public function test_auto_ordering_assigns_incremental_values(): void
    {
        $service = app('flow-task');
        $source = $this->makeFlowWithTransition('TK9');

        $res1 = $service->store([
            'flow_transition_id' => $source['transition']->id,
            'driver'             => DummyActionTask::class,
            'config'             => [
                'message' => 'First',
            ],
            'status'             => true,
        ]);

        $res2 = $service->store([
            'flow_transition_id' => $source['transition']->id,
            'driver'             => DummyValidationTask::class,
            'config'             => [
                'min_amount' => 10,
            ],
            'status'             => true,
        ]);

        $this->assertTrue($res1->ok);
        $this->assertTrue($res2->ok);

        /** @var FlowTaskModel $task1 */
        $task1 = $res1->data->resource;
        /** @var FlowTaskModel $task2 */
        $task2 = $res2->data->resource;

        $this->assertEquals(0, $task1->ordering);
        $this->assertEquals(1, $task2->ordering);
    }
}

