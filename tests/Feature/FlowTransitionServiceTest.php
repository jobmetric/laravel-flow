<?php

namespace JobMetric\Flow\Tests\Feature;

use Illuminate\Validation\ValidationException;
use JobMetric\Flow\DTO\TransitionResult;
use JobMetric\Flow\Exceptions\TaskRestrictionException;
use JobMetric\Flow\Http\Resources\FlowTransitionResource;
use JobMetric\Flow\Models\Flow as FlowModel;
use JobMetric\Flow\Models\FlowInstance;
use JobMetric\Flow\Models\FlowState as FlowStateModel;
use JobMetric\Flow\Models\FlowTransition as FlowTransitionModel;
use JobMetric\Flow\Services\Flow as FlowService;
use JobMetric\Flow\Services\FlowState as FlowStateService;
use JobMetric\Flow\Services\FlowTask as FlowTaskService;
use JobMetric\Flow\Services\FlowTransition as FlowTransitionService;
use JobMetric\Flow\Support\FlowTaskRegistry;
use JobMetric\Flow\Tests\Stubs\Enums\OrderStatusEnum;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Flow\Tests\Stubs\Models\User;
use JobMetric\Flow\Tests\Stubs\Tasks\DummyActionTask;
use JobMetric\Flow\Tests\Stubs\Tasks\DummyRestrictionTask;
use JobMetric\Flow\Tests\Stubs\Tasks\DummyValidationTask;
use JobMetric\Flow\Tests\TestCase as BaseTestCase;
use LogicException;
use RuntimeException;
use Throwable;

class FlowTransitionServiceTest extends BaseTestCase
{
    /**
     * @var FlowTaskRegistry
     */
    protected FlowTaskRegistry $taskRegistry;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Register all stub tasks for runner tests
        // Note: Use string key 'FlowTaskRegistry' to match how it's resolved in validation
        $this->taskRegistry = app('FlowTaskRegistry');

        // Register tasks only if not already registered (to avoid duplicate registration errors)
        if (! $this->taskRegistry->hasClass(DummyActionTask::class)) {
            $this->taskRegistry->register(new DummyActionTask);
        }

        if (! $this->taskRegistry->hasClass(DummyValidationTask::class)) {
            $this->taskRegistry->register(new DummyValidationTask);
        }

        if (! $this->taskRegistry->hasClass(DummyRestrictionTask::class)) {
            $this->taskRegistry->register(new DummyRestrictionTask);
        }
    }

    /**
     * Build a valid flow payload (same pattern as FlowStateServiceTest).
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
     * Create a Flow using FlowService (auto-creates START) and add two normal states.
     *
     * @param string $scope
     *
     * @return array{flow: FlowModel, start: FlowStateModel, a: FlowStateModel, b: FlowStateModel}
     * @throws Throwable
     */
    protected function makeFlowWithStartAndTwoStates(string $scope): array
    {
        $flowService = app(FlowService::class);
        $stateService = app(FlowStateService::class);

        $user = User::factory()->setName('Taraneh-Dad')->create();

        $created = $flowService->store($this->makeFlowPayload(Order::class, $scope ?: (string) $user->id));
        $this->assertTrue($created->ok);

        /** @var FlowModel $flow */
        $flow = FlowModel::query()->findOrFail($created->data->id);

        $start = $flowService->getStartState($flow->id);
        $this->assertNotNull($start, 'START state must exist (created by FlowService::store)');
        $this->assertTrue($start->is_start, 'getStartState() must return state flagged as start');

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

        return compact('flow', 'start', 'a', 'b');
    }

    /**
     * Store: happy path — first legal transition START -> A with valid slug.
     *
     * @throws Throwable
     */
    public function test_store_creates_transition_with_valid_payload(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX1');

        $res = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
            'slug'        => 'order-create',
        ]);

        $this->assertTrue($res->ok);
        /** @var FlowTransitionModel $t */
        $t = $res->data->resource;

        $this->assertEquals($source['flow']->id, $t->flow_id);
        $this->assertEquals($source['start']->id, $t->from);
        $this->assertEquals($source['a']->id, $t->to);
        $this->assertEquals('order-create', $t->slug);
        $this->assertFalse($t->is_start_edge);
        $this->assertFalse($t->is_end_edge);
    }

    /**
     * Store: first transition must originate from START (negative).
     *
     * @throws Throwable
     */
    public function test_store_first_transition_must_start_from_start(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX2');

        $this->expectException(ValidationException::class);
        $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'AtoB',
                ],
            ],
            'from'        => $source['a']->id,
            'to'          => $source['b']->id,
        ]);
    }

    /**
     * Store: from == to is allowed for self-loop (except for start state).
     *
     * @throws Throwable
     */
    public function test_store_allows_self_loop_except_for_start_state(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX3');

        // First legal transition (to satisfy "first must be from START")
        $this->assertTrue($service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->ok);

        // Self-loop is now allowed for non-start states
        $selfLoop = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'AToA',
                ],
            ],
            'from'        => $source['a']->id,
            'to'          => $source['a']->id,
        ]);

        $this->assertTrue($selfLoop->ok);

        // But self-loop is not allowed for start state
        $this->expectException(ValidationException::class);
        $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToStart',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['start']->id,
        ]);
    }

    /**
     * Store: "to" cannot point to START.
     *
     * @throws Throwable
     */
    public function test_store_rejects_to_pointing_to_start(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX4');

        $this->assertTrue($service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->ok);

        $this->expectException(ValidationException::class);
        $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'BadToStart',
                ],
            ],
            'from'        => $source['a']->id,
            'to'          => $source['start']->id,
        ]);
    }

    /**
     * Store: duplicate (flow_id, from, to) pair must fail.
     *
     * @throws Throwable
     */
    public function test_store_duplicate_pair_fails(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX5');

        $this->assertTrue($service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->ok);

        $this->expectException(ValidationException::class);
        $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'Dup',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ]);
    }

    /**
     * Store: duplicate translated "name" in same flow should fail.
     *
     * @throws Throwable
     */
    public function test_store_duplicate_translation_name_in_same_flow_fails(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX6');

        $this->assertTrue($service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'UniqueName',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->ok);

        $this->expectException(ValidationException::class);
        $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'UniqueName',
                ],
            ],
            'from'        => $source['a']->id,
            'to'          => $source['b']->id,
        ]);
    }

    /**
     * Update: can change slug/name; self-loop is allowed except for start state.
     *
     * @throws Throwable
     */
    public function test_update_changes_fields_and_allows_self_loop_except_start(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX7');

        $tSA = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
            'slug'        => 's-a',
        ])->data->resource;

        $tAB = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'AToB',
                ],
            ],
            'from'        => $source['a']->id,
            'to'          => $source['b']->id,
            'slug'        => 'a-b',
        ])->data->resource;

        $upd = $service->update($tAB->id, [
            'translation' => [
                'en' => [
                    'name' => 'AToB v2',
                ],
            ],
            'slug'        => 'a-b-v2',
        ]);
        $this->assertTrue($upd->ok);

        // Self-loop is now allowed for non-start states
        $selfLoop = $service->update($tAB->id, [
            'from' => $source['a']->id,
            'to'   => $source['a']->id,
        ]);
        $this->assertTrue($selfLoop->ok);

        // But self-loop is not allowed for start state
        $this->expectException(ValidationException::class);
        $service->update($tSA->id, [
            'from' => $source['start']->id,
            'to'   => $source['start']->id,
        ]);
    }

    /**
     * Update: duplicate (flow_id, from, to) after update rejected.
     *
     * @throws Throwable
     */
    public function test_update_duplicate_pair_rejected(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX8');

        $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->data->resource;

        $tAB = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'AToB',
                ],
            ],
            'from'        => $source['a']->id,
            'to'          => $source['b']->id,
        ])->data->resource;

        $tBA = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'BToA',
                ],
            ],
            'from'        => $source['b']->id,
            'to'          => $source['a']->id,
        ])->data->resource;

        $this->expectException(ValidationException::class);
        $service->update($tBA->id, [
            'from' => $source['a']->id,
            'to'   => $source['b']->id, // collides with existing A->B
        ]);
    }

    /**
     * Update: cannot set "to" as START.
     *
     * @throws Throwable
     */
    public function test_update_cannot_set_to_start(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX9');

        $t = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->data->resource;

        $this->expectException(ValidationException::class);
        $service->update($t->id, [
            'to' => $source['start']->id,
        ]);
    }

    /**
     * Destroy: cannot delete START→* if another START→* exists.
     *
     * @throws Throwable
     */
    public function test_destroy_rejects_deleting_non_last_start_transition(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX10');

        $tSA = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->data->resource;

        $tSB = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'AToB',
                ],
            ],
            'from'        => $source['a']->id,
            'to'          => $source['b']->id,
        ])->data->resource;

        $this->expectException(RuntimeException::class);
        $service->doDestroy($tSA->id);
    }

    /**
     * Model Scopes & Accessors: startEdges(), endEdges(), is_start_edge/is_end_edge.
     * (insert raw rows to allow NULL endpoints; service validators don't allow them)
     */
    public function test_model_scopes_start_and_end_and_accessors(): void
    {
        $flowService = app(FlowService::class);
        $stateService = app(FlowStateService::class);

        $created = $flowService->store($this->makeFlowPayload(Order::class, 'TX11'));
        $this->assertTrue($created->ok);

        /** @var FlowModel $flow */
        $flow = FlowModel::query()->findOrFail($created->data->id);

        $start = $flowService->getStartState($flow->id);
        $this->assertNotNull($start);

        $a = $stateService->store([
            'flow_id'     => $flow->id,
            'translation' => [
                'en' => [
                    'name'        => 'A',
                    'description' => null,
                ],
            ],
            'status'      => OrderStatusEnum::PENDING(),
        ])->data->resource;

        $b = $stateService->store([
            'flow_id'     => $flow->id,
            'translation' => [
                'en' => [
                    'name'        => 'B',
                    'description' => null,
                ],
            ],
            'status'      => OrderStatusEnum::PENDING(),
        ])->data->resource;

        $startEdge = FlowTransitionModel::query()->create([
            'flow_id' => $flow->id,
            'from'    => null,
            'to'      => $start->id,
            'slug'    => null,
        ]);

        $normal = FlowTransitionModel::query()->create([
            'flow_id' => $flow->id,
            'from'    => $a->id,
            'to'      => $b->id,
            'slug'    => 'a-b',
        ]);

        $endEdge = FlowTransitionModel::query()->create([
            'flow_id' => $flow->id,
            'from'    => $b->id,
            'to'      => null,
            'slug'    => null,
        ]);

        $starts = FlowTransitionModel::startEdges()->where('flow_id', $flow->id)->get();
        $ends = FlowTransitionModel::endEdges()->where('flow_id', $flow->id)->get();

        $this->assertCount(1, $starts);
        $this->assertEquals($startEdge->id, $starts->first()->id);
        $this->assertTrue($starts->first()->is_start_edge);

        $this->assertCount(1, $ends);
        $this->assertEquals($endEdge->id, $ends->first()->id);
        $this->assertTrue($ends->first()->is_end_edge);

        $this->assertFalse($normal->is_start_edge);
        $this->assertFalse($normal->is_end_edge);
    }

    /**
     * Resource: FlowTransitionResource structure and loaded relations.
     */
    public function test_resource_structure(): void
    {
        $flowService = app(FlowService::class);
        $stateService = app(FlowStateService::class);

        $created = $flowService->store($this->makeFlowPayload(Order::class, 'TX12'));
        $this->assertTrue($created->ok);

        /** @var FlowModel $flow */
        $flow = FlowModel::query()->findOrFail($created->data->id);

        $start = $flowService->getStartState($flow->id);
        $this->assertNotNull($start);

        $a = $stateService->store([
            'flow_id'     => $flow->id,
            'translation' => [
                'en' => [
                    'name'        => 'A',
                    'description' => null,
                ],
            ],
            'status'      => OrderStatusEnum::PENDING(),
        ])->data->resource;

        $b = $stateService->store([
            'flow_id'     => $flow->id,
            'translation' => [
                'en' => [
                    'name'        => 'B',
                    'description' => null,
                ],
            ],
            'status'      => OrderStatusEnum::PENDING(),
        ])->data->resource;

        $t = FlowTransitionModel::query()->create([
            'flow_id' => $flow->id,
            'from'    => $a->id,
            'to'      => $b->id,
            'slug'    => 'a-b',
        ])->load(['flow', 'fromState', 'toState', 'tasks', 'instances']);

        $arr = FlowTransitionResource::make($t)->toArray(request());

        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('flow_id', $arr);
        $this->assertArrayHasKey('from', $arr);
        $this->assertArrayHasKey('to', $arr);
        $this->assertArrayHasKey('slug', $arr);
        $this->assertArrayHasKey('is_start_edge', $arr);
        $this->assertArrayHasKey('is_end_edge', $arr);
        $this->assertArrayHasKey('created_at', $arr);
        $this->assertArrayHasKey('updated_at', $arr);
        $this->assertArrayHasKey('flow', $arr);
        $this->assertArrayHasKey('from_state', $arr);
        $this->assertArrayHasKey('to_state', $arr);
        $this->assertArrayHasKey('tasks', $arr);
        $this->assertArrayHasKey('instances', $arr);
    }

    /**
     * Runner: execute transition successfully without tasks.
     *
     * @throws Throwable
     */
    public function test_runner_executes_transition_successfully_without_tasks(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $stateService = app(FlowStateService::class);
        $source = $this->makeFlowWithStartAndTwoStates('RUN1');

        // Create a state without status to test that status doesn't change
        $stateWithoutStatus = $stateService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name'        => 'StateWithoutStatus',
                    'description' => null,
                ],
            ],
            'status'      => null,
            'is_terminal' => false,
        ])->data->resource;

        $transition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'StartToStateWithoutStatus'],
            ],
            'from'        => $source['start']->id,
            'to'          => $stateWithoutStatus->id,
            'slug'        => 'start-to-state-without-status',
        ])->data->resource;

        $order = Order::factory()
            ->setUserID($source['flow']->subject_scope ? (int) $source['flow']->subject_scope : 1)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $initialStatus = $order->status;

        $result = $transitionService->runner($transition->id, $order);

        $this->assertInstanceOf(TransitionResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEmpty($result->getErrors());

        // Check FlowInstance was created
        $instance = FlowInstance::query()->forModel($order)->first();
        $this->assertNotNull($instance);
        $this->assertEquals($transition->id, $instance->flow_transition_id);
        $this->assertNull($instance->completed_at); // Not an end state

        // Check that status didn't change (state has no status)
        $order->refresh();
        $this->assertEquals($initialStatus, $order->status);
    }

    /**
     * Runner: execute transition with restriction task (allowed).
     *
     * @throws Throwable
     */
    public function test_runner_executes_transition_with_allowed_restriction_task(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $taskService = app(FlowTaskService::class);
        $source = $this->makeFlowWithStartAndTwoStates('RUN2');

        $transition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'StartToA'],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
            'slug'        => 'start-to-a',
        ])->data->resource;

        // Add restriction task
        $taskService->store([
            'flow_transition_id' => $transition->id,
            'driver'             => DummyRestrictionTask::class,
            'config'             => ['allow_transition' => true],
            'status'             => true,
        ]);

        $order = Order::factory()
            ->setUserID($source['flow']->subject_scope ? (int) $source['flow']->subject_scope : 1)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $result = $transitionService->runner($transition->id, $order);

        $this->assertTrue($result->isSuccess());
    }

    /**
     * Runner: execute transition with restriction task (denied).
     *
     * @throws Throwable
     */
    public function test_runner_fails_with_denied_restriction_task(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $taskService = app(FlowTaskService::class);
        $source = $this->makeFlowWithStartAndTwoStates('RUN3');

        $transition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'StartToA'],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
            'slug'        => 'start-to-a',
        ])->data->resource;

        // Add restriction task that denies
        $taskService->store([
            'flow_transition_id' => $transition->id,
            'driver'             => DummyRestrictionTask::class,
            'config'             => ['allow_transition' => false],
            'status'             => true,
        ]);

        $order = Order::factory()
            ->setUserID($source['flow']->subject_scope ? (int) $source['flow']->subject_scope : 1)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        // Verify task is registered
        $this->assertTrue($this->taskRegistry->hasClass(DummyRestrictionTask::class));

        $this->expectException(TaskRestrictionException::class);
        $transitionService->runner($transition->id, $order);
    }

    /**
     * Runner: execute transition with validation task (success).
     *
     * @throws Throwable
     */
    public function test_runner_executes_transition_with_passing_validation_task(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $taskService = app(FlowTaskService::class);
        $source = $this->makeFlowWithStartAndTwoStates('RUN4');

        $transition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'StartToA'],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
            'slug'        => 'start-to-a',
        ])->data->resource;

        // Add validation task
        $taskService->store([
            'flow_transition_id' => $transition->id,
            'driver'             => DummyValidationTask::class,
            'config'             => ['min_amount' => 100],
            'status'             => true,
        ]);

        $order = Order::factory()
            ->setUserID($source['flow']->subject_scope ? (int) $source['flow']->subject_scope : 1)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $result = $transitionService->runner($transition->id, $order, ['amount' => 150]);

        $this->assertTrue($result->isSuccess());
    }

    /**
     * Runner: execute transition with validation task (failure).
     *
     * @throws Throwable
     */
    public function test_runner_fails_with_failing_validation_task(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $taskService = app(FlowTaskService::class);
        $source = $this->makeFlowWithStartAndTwoStates('RUN5');

        $transition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'StartToA'],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
            'slug'        => 'start-to-a',
        ])->data->resource;

        // Add validation task
        $taskService->store([
            'flow_transition_id' => $transition->id,
            'driver'             => DummyValidationTask::class,
            'config'             => ['min_amount' => 100],
            'status'             => true,
        ]);

        $order = Order::factory()
            ->setUserID($source['flow']->subject_scope ? (int) $source['flow']->subject_scope : 1)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        // Verify task is registered
        $this->assertTrue($this->taskRegistry->hasClass(DummyValidationTask::class));

        $this->expectException(ValidationException::class);
        $transitionService->runner($transition->id, $order, ['amount' => 50]); // Less than min_amount
    }

    /**
     * Runner: execute transition with action task.
     *
     * @throws Throwable
     */
    public function test_runner_executes_transition_with_action_task(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $taskService = app(FlowTaskService::class);
        $source = $this->makeFlowWithStartAndTwoStates('RUN6');

        $transition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'StartToA'],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
            'slug'        => 'start-to-a',
        ])->data->resource;

        // Add action task
        $taskService->store([
            'flow_transition_id' => $transition->id,
            'driver'             => DummyActionTask::class,
            'config'             => ['message' => 'Test message', 'retries' => 3],
            'status'             => true,
        ]);

        $order = Order::factory()
            ->setUserID($source['flow']->subject_scope ? (int) $source['flow']->subject_scope : 1)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $result = $transitionService->runner($transition->id, $order);

        $this->assertTrue($result->isSuccess());
    }

    /**
     * Runner: updates model status after transition.
     *
     * @throws Throwable
     */
    public function test_runner_updates_model_status_after_transition(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $stateService = app(FlowStateService::class);
        $source = $this->makeFlowWithStartAndTwoStates('RUN7');

        // Update state A to have a status
        $stateA = $source['a'];
        $stateA->status = OrderStatusEnum::NEED_CONFIRM->value;
        $stateA->save();

        $transition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'StartToA'],
            ],
            'from'        => $source['start']->id,
            'to'          => $stateA->id,
            'slug'        => 'start-to-a',
        ])->data->resource;

        $order = Order::factory()
            ->setUserID($source['flow']->subject_scope ? (int) $source['flow']->subject_scope : 1)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $initialStatus = $order->status;

        $transitionService->runner($transition->id, $order);

        $order->refresh();
        $order->refresh();
        $this->assertNotEquals($initialStatus, $order->status);
        // Check if status is enum or string and compare accordingly
        $expectedValue = OrderStatusEnum::NEED_CONFIRM->value;
        $actualValue = $order->status instanceof OrderStatusEnum ? $order->status->value : $order->status;
        $this->assertEquals($expectedValue, $actualValue);
    }

    /**
     * Runner: creates FlowInstance on first transition.
     *
     * @throws Throwable
     */
    public function test_runner_creates_flow_instance_on_first_transition(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('RUN8');

        $transition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'StartToA'],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
            'slug'        => 'start-to-a',
        ])->data->resource;

        $order = Order::factory()
            ->setUserID($source['flow']->subject_scope ? (int) $source['flow']->subject_scope : 1)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $this->assertDatabaseMissing('flow_instances', [
            'instanceable_type' => Order::class,
            'instanceable_id'   => $order->id,
        ]);

        $transitionService->runner($transition->id, $order);

        $this->assertDatabaseHas('flow_instances', [
            'instanceable_type'  => Order::class,
            'instanceable_id'    => $order->id,
            'flow_transition_id' => $transition->id,
        ]);
    }

    /**
     * Runner: updates existing FlowInstance on subsequent transition.
     *
     * @throws Throwable
     */
    public function test_runner_updates_existing_flow_instance_on_subsequent_transition(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('RUN9');

        $transition1 = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'StartToA'],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
            'slug'        => 'start-to-a',
        ])->data->resource;

        $transition2 = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'AToB'],
            ],
            'from'        => $source['a']->id,
            'to'          => $source['b']->id,
            'slug'        => 'a-to-b',
        ])->data->resource;

        $order = Order::factory()
            ->setUserID($source['flow']->subject_scope ? (int) $source['flow']->subject_scope : 1)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        // Execute first transition
        $transitionService->runner($transition1->id, $order);

        $instance1 = FlowInstance::query()->forModel($order)->first();
        $this->assertEquals($transition1->id, $instance1->flow_transition_id);

        // Execute second transition
        $transitionService->runner($transition2->id, $order);

        $instance2 = FlowInstance::query()->forModel($order)->first();
        $this->assertEquals($instance1->id, $instance2->id); // Same instance
        $this->assertEquals($transition2->id, $instance2->flow_transition_id); // Updated transition
    }

    /**
     * Runner: marks FlowInstance as completed when reaching end state.
     *
     * @throws Throwable
     */
    public function test_runner_marks_instance_completed_when_reaching_end_state(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $stateService = app(FlowStateService::class);
        $source = $this->makeFlowWithStartAndTwoStates('RUN10');

        // Create an end state
        $endState = $stateService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'Completed', 'description' => null],
            ],
            'status'      => OrderStatusEnum::PAID(),
            'is_terminal' => true,
        ])->data->resource;

        // First create a transition from start to state A (required for first transition)
        $transitionToA = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'StartToA'],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
            'slug'        => 'start-to-a',
        ])->data->resource;

        // Then create transition from A to end state
        $transition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'ToEnd'],
            ],
            'from'        => $source['a']->id,
            'to'          => $endState->id,
            'slug'        => 'to-end',
        ])->data->resource;

        $order = Order::factory()
            ->setUserID($source['flow']->subject_scope ? (int) $source['flow']->subject_scope : 1)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        // First execute transition from start to A
        $transitionService->runner($transitionToA->id, $order);

        // Then execute transition from A to end state
        $transitionService->runner($transition->id, $order);

        $instance = FlowInstance::query()->forModel($order)->first();
        $this->assertNotNull($instance->completed_at);
    }

    /**
     * Runner: fails when subject model is not provided and no FlowInstance exists.
     *
     * @throws Throwable
     */
    public function test_runner_fails_when_subject_not_provided_and_no_instance_exists(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('RUN11');

        $transition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'StartToA'],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
            'slug'        => 'start-to-a',
        ])->data->resource;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(trans('workflow::base.errors.flow_transition.subject_model_required'));

        $transitionService->runner($transition->id);
    }

    /**
     * Runner: fails when subject model type doesn't match flow subject type.
     *
     * @throws Throwable
     */
    public function test_runner_fails_when_subject_type_mismatch(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('RUN12');

        $transition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'StartToA'],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
            'slug'        => 'start-to-a',
        ])->data->resource;

        $user = User::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(trans('workflow::base.errors.flow_transition.subject_model_type_mismatch', [
            'expected' => Order::class,
            'got'      => User::class,
        ]));

        $transitionService->runner($transition->id, $user);
    }

    /**
     * Runner: can execute transition by slug.
     *
     * @throws Throwable
     */
    public function test_runner_can_execute_transition_by_slug(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('RUN13');

        $transition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'StartToA'],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
            'slug'        => 'my-custom-slug',
        ])->data->resource;

        $order = Order::factory()
            ->setUserID($source['flow']->subject_scope ? (int) $source['flow']->subject_scope : 1)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        $result = $transitionService->runner('my-custom-slug', $order);

        $this->assertTrue($result->isSuccess());
    }

    /**
     * Runner: executes tasks in correct order (restriction -> validation -> action).
     * Note: Tasks are executed by type first (restriction, then validation, then action),
     * and within each type, they are ordered by the 'ordering' field.
     *
     * @throws Throwable
     */
    public function test_runner_executes_tasks_in_correct_order(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $taskService = app(FlowTaskService::class);
        $source = $this->makeFlowWithStartAndTwoStates('RUN14');

        $transition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => ['name' => 'StartToA'],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
            'slug'        => 'start-to-a',
        ])->data->resource;

        // Add tasks in different order - they should execute in type order
        // (restriction -> validation -> action), regardless of ordering field
        $taskService->store([
            'flow_transition_id' => $transition->id,
            'driver'             => DummyActionTask::class,
            'config'             => ['message' => 'Action', 'retries' => 1],
            'status'             => true,
            'ordering'           => 0,
        ]);

        $taskService->store([
            'flow_transition_id' => $transition->id,
            'driver'             => DummyValidationTask::class,
            'config'             => ['min_amount' => 50],
            'status'             => true,
            'ordering'           => 1,
        ]);

        $taskService->store([
            'flow_transition_id' => $transition->id,
            'driver'             => DummyRestrictionTask::class,
            'config'             => ['allow_transition' => true],
            'status'             => true,
            'ordering'           => 2,
        ]);

        $order = Order::factory()
            ->setUserID($source['flow']->subject_scope ? (int) $source['flow']->subject_scope : 1)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        // Should execute: restriction (ordering 2) -> validation (ordering 1) -> action (ordering 0)
        // Type order takes precedence over ordering field
        $result = $transitionService->runner($transition->id, $order, ['amount' => 100]);

        $this->assertTrue($result->isSuccess());
    }

    /**
     * Store: self-loop transition is allowed (from == to, but not for start state).
     *
     * @throws Throwable
     */
    public function test_store_allows_self_loop_transition(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('SELF1');

        // First create a transition from start to A
        $this->assertTrue($service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->ok);

        // Now create a self-loop transition from A to A
        $res = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'AToA',
                ],
            ],
            'from'        => $source['a']->id,
            'to'          => $source['a']->id,
        ]);

        $this->assertTrue($res->ok);
        /** @var FlowTransitionModel $t */
        $t = $res->data->resource;
        $this->assertEquals($source['a']->id, $t->from);
        $this->assertEquals($source['a']->id, $t->to);
        $this->assertTrue($t->is_self_loop_transition);
    }

    /**
     * Store: self-loop transition is not allowed for start state.
     *
     * @throws Throwable
     */
    public function test_store_rejects_self_loop_for_start_state(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('SELF2');

        // First create a transition from start to A
        $this->assertTrue($service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->ok);

        // Try to create a self-loop transition from start to start
        $this->expectException(ValidationException::class);
        $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToStart',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['start']->id,
        ]);
    }

    /**
     * Store: generic input transition (from = null) is allowed.
     *
     * @throws Throwable
     */
    public function test_store_allows_generic_input_transition(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('GENIN1');

        // First create a transition from start to A
        $this->assertTrue($service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->ok);

        // Create a generic input transition (from = null, to = A)
        $res = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'GenericInputToA',
                ],
            ],
            'from'        => null,
            'to'          => $source['a']->id,
        ]);

        $this->assertTrue($res->ok);
        /** @var FlowTransitionModel $t */
        $t = $res->data->resource;
        $this->assertNull($t->from);
        $this->assertEquals($source['a']->id, $t->to);
        $this->assertTrue($t->is_generic_input_transition);
    }

    /**
     * Store: generic output transition (to = null) is allowed.
     *
     * @throws Throwable
     */
    public function test_store_allows_generic_output_transition(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('GENOUT1');

        // First create a transition from start to A
        $this->assertTrue($service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->ok);

        // Create a generic output transition (from = A, to = null)
        $res = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'AToGenericOutput',
                ],
            ],
            'from'        => $source['a']->id,
            'to'          => null,
        ]);

        $this->assertTrue($res->ok);
        /** @var FlowTransitionModel $t */
        $t = $res->data->resource;
        $this->assertEquals($source['a']->id, $t->from);
        $this->assertNull($t->to);
        $this->assertTrue($t->is_generic_output_transition);
    }

    /**
     * Store: generic output transition is not allowed for terminal state.
     *
     * @throws Throwable
     */
    public function test_store_rejects_generic_output_for_terminal_state(): void
    {
        $service = app(FlowTransitionService::class);
        $stateService = app(FlowStateService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TERM1');

        // Create a terminal state
        $terminalState = $stateService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name'        => 'Terminal',
                    'description' => null,
                ],
            ],
            'status'      => OrderStatusEnum::PAID(),
            'is_terminal' => true,
        ])->data->resource;

        // First create a transition from start to A
        $this->assertTrue($service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->ok);

        // Try to create a generic output transition from terminal state
        $this->expectException(ValidationException::class);
        $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'TerminalToGenericOutput',
                ],
            ],
            'from'        => $terminalState->id,
            'to'          => null,
        ]);
    }

    /**
     * Store: at least one of from or to must be set.
     *
     * @throws Throwable
     */
    public function test_store_rejects_both_null(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('BOTHNULL1');

        // First create a transition from start to A
        $this->assertTrue($service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->ok);

        // Try to create a transition with both from and to as null
        $this->expectException(ValidationException::class);
        $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'BothNull',
                ],
            ],
            'from'        => null,
            'to'          => null,
        ]);
    }

    /**
     * Model accessors: test transition type accessors.
     *
     * @throws Throwable
     */
    public function test_model_accessors_for_transition_types(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('ACC1');

        // Create start transition
        $startTransition = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->data->resource;

        $this->assertTrue($startTransition->is_start_transition);
        $this->assertTrue($startTransition->is_specific_transition);
        $this->assertFalse($startTransition->is_self_loop_transition);
        $this->assertFalse($startTransition->is_generic_input_transition);
        $this->assertFalse($startTransition->is_generic_output_transition);

        // Create self-loop transition
        $selfLoop = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'AToA',
                ],
            ],
            'from'        => $source['a']->id,
            'to'          => $source['a']->id,
        ])->data->resource;

        $this->assertFalse($selfLoop->is_start_transition);
        $this->assertFalse($selfLoop->is_specific_transition);
        $this->assertTrue($selfLoop->is_self_loop_transition);
        $this->assertFalse($selfLoop->is_generic_input_transition);
        $this->assertFalse($selfLoop->is_generic_output_transition);

        // Create generic input transition
        $genericInput = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'GenericToA',
                ],
            ],
            'from'        => null,
            'to'          => $source['a']->id,
        ])->data->resource;

        $this->assertFalse($genericInput->is_start_transition);
        $this->assertFalse($genericInput->is_specific_transition);
        $this->assertFalse($genericInput->is_self_loop_transition);
        $this->assertTrue($genericInput->is_generic_input_transition);
        $this->assertFalse($genericInput->is_generic_output_transition);

        // Create generic output transition
        $genericOutput = $service->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'AToGeneric',
                ],
            ],
            'from'        => $source['a']->id,
            'to'          => null,
        ])->data->resource;

        $this->assertFalse($genericOutput->is_start_transition);
        $this->assertFalse($genericOutput->is_specific_transition);
        $this->assertFalse($genericOutput->is_self_loop_transition);
        $this->assertFalse($genericOutput->is_generic_input_transition);
        $this->assertTrue($genericOutput->is_generic_output_transition);
    }

    /**
     * Runner: can execute generic input transition independently.
     *
     * @throws Throwable
     */
    public function test_runner_can_execute_generic_input_transition_independently(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('GENINRUN1');

        // First create a transition from start to A (required for first transition)
        $this->assertTrue($transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->ok);

        // Create a generic input transition (from = null, to = A)
        $genericInputTransition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'GenericInputToA',
                ],
            ],
            'from'        => null,
            'to'          => $source['a']->id,
            'slug'        => 'generic-input-to-a',
        ])->data->resource;

        $order = Order::factory()
            ->setUserID($source['flow']->subject_scope ? (int) $source['flow']->subject_scope : 1)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        // Execute the generic input transition directly
        $result = $transitionService->runner($genericInputTransition->id, $order);

        $this->assertTrue($result->isSuccess());

        // Check FlowInstance was created
        $instance = FlowInstance::query()->forModel($order)->first();
        $this->assertNotNull($instance);
        $this->assertEquals($genericInputTransition->id, $instance->flow_transition_id);
    }

    /**
     * Runner: generic input transition can be executed even when state has no specific transitions.
     *
     * @throws Throwable
     */
    public function test_runner_generic_input_works_when_state_has_no_specific_transitions(): void
    {
        $transitionService = app(FlowTransitionService::class);
        $stateService = app(FlowStateService::class);
        $source = $this->makeFlowWithStartAndTwoStates('GENINRUN2');

        // First create a transition from start to A (required for first transition)
        $this->assertTrue($transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA',
                ],
            ],
            'from'        => $source['start']->id,
            'to'          => $source['a']->id,
        ])->ok);

        // Create a new state that will only be reachable via generic input
        $isolatedState = $stateService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name'        => 'IsolatedState',
                    'description' => 'State only reachable via generic input',
                ],
            ],
            'status'      => OrderStatusEnum::PENDING(),
            'is_terminal' => false,
        ])->data->resource;

        // Create a generic input transition to this isolated state
        $genericInputTransition = $transitionService->store([
            'flow_id'     => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'GenericInputToIsolated',
                ],
            ],
            'from'        => null,
            'to'          => $isolatedState->id,
            'slug'        => 'generic-input-to-isolated',
        ])->data->resource;

        $order = Order::factory()
            ->setUserID($source['flow']->subject_scope ? (int) $source['flow']->subject_scope : 1)
            ->setStatus(OrderStatusEnum::PENDING())
            ->create();

        // Execute the generic input transition - should work even though isolatedState has no incoming specific transitions
        $result = $transitionService->runner($genericInputTransition->id, $order);

        $this->assertTrue($result->isSuccess());

        // Check FlowInstance was created
        $instance = FlowInstance::query()->forModel($order)->first();
        $this->assertNotNull($instance);
        $this->assertEquals($genericInputTransition->id, $instance->flow_transition_id);
    }
}
