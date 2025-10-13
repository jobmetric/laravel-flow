<?php

namespace JobMetric\Flow\Tests\Feature;

use Illuminate\Validation\ValidationException;
use JobMetric\Flow\Http\Resources\FlowTransitionResource;
use JobMetric\Flow\Models\Flow as FlowModel;
use JobMetric\Flow\Models\FlowState as FlowStateModel;
use JobMetric\Flow\Models\FlowTransition as FlowTransitionModel;
use JobMetric\Flow\Services\Flow as FlowService;
use JobMetric\Flow\Services\FlowState as FlowStateService;
use JobMetric\Flow\Services\FlowTransition as FlowTransitionService;
use JobMetric\Flow\Tests\Stubs\Enums\OrderStatusEnum;
use JobMetric\Flow\Tests\Stubs\Models\Order;
use JobMetric\Flow\Tests\Stubs\Models\User;
use JobMetric\Flow\Tests\TestCase as BaseTestCase;
use RuntimeException;
use Throwable;

class FlowTransitionServiceTest extends BaseTestCase
{
    /**
     * Build a valid flow payload (same pattern as FlowStateServiceTest).
     *
     * @param string $subject
     * @param string|null $scope
     * @param int $version
     * @param bool $isDefault
     * @param bool $status
     * @param string|null $title
     * @return array<string,mixed>
     */
    protected function makeFlowPayload(string $subject, ?string $scope, int $version = 1, bool $isDefault = true, bool $status = true, ?string $title = null): array
    {
        $translation = [
            'en' => [
                'name' => $title ?? "Test Flow v{$version}",
                'description' => "Desc v{$version}",
            ],
        ];

        return [
            'subject_type' => $subject,
            'subject_scope' => $scope,
            'subject_collection' => null,
            'version' => $version,
            'is_default' => $isDefault,
            'status' => $status,
            'active_from' => null,
            'active_to' => null,
            'channel' => 'web',
            'ordering' => 0,
            'rollout_pct' => null,
            'environment' => 'test',
            'translation' => $translation,
        ];
    }

    /**
     * Create a Flow using FlowService (auto-creates START) and add two normal states.
     *
     * @param string $scope
     * @return array{flow: FlowModel, start: FlowStateModel, a: FlowStateModel, b: FlowStateModel}
     * @throws Throwable
     */
    protected function makeFlowWithStartAndTwoStates(string $scope): array
    {
        $flowService = app(FlowService::class);
        $stateService = app(FlowStateService::class);

        $user = User::factory()->setName('Taraneh-Dad')->create();

        $created = $flowService->store(
            $this->makeFlowPayload(Order::class, $scope ?: (string)$user->id)
        );
        $this->assertTrue($created->ok);

        /** @var FlowModel $flow */
        $flow = FlowModel::query()->findOrFail($created->data->id);

        $start = $flowService->getStartState($flow->id);
        $this->assertNotNull($start, 'START state must exist (created by FlowService::store)');
        $this->assertTrue($start->is_start, 'getStartState() must return state flagged as start');

        /** @var FlowStateModel $a */
        $a = $stateService->store([
            'flow_id' => $flow->id,
            'translation' => [
                'en' => [
                    'name' => 'StateA',
                    'description' => null
                ]
            ],
            'status' => OrderStatusEnum::PENDING(),
            'is_terminal' => false,
        ])->data->resource;

        /** @var FlowStateModel $b */
        $b = $stateService->store([
            'flow_id' => $flow->id,
            'translation' => [
                'en' => [
                    'name' => 'StateB',
                    'description' => null
                ]
            ],
            'status' => OrderStatusEnum::PENDING(),
            'is_terminal' => false,
        ])->data->resource;

        return compact('flow', 'start', 'a', 'b');
    }

    /**
     * Store: happy path — first legal transition START -> A with valid slug.
     * @throws Throwable
     */
    public function test_store_creates_transition_with_valid_payload(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX1');

        $res = $service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA'
                ]
            ],
            'from' => $source['start']->id,
            'to' => $source['a']->id,
            'slug' => 'order-create',
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
     * @throws Throwable
     */
    public function test_store_first_transition_must_start_from_start(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX2');

        $this->expectException(ValidationException::class);
        $service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'AtoB'
                ]
            ],
            'from' => $source['a']->id,
            'to' => $source['b']->id,
        ]);
    }

    /**
     * Store: from == to is rejected.
     * @throws Throwable
     */
    public function test_store_rejects_equal_from_and_to(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX3');

        // First legal transition (to satisfy "first must be from START")
        $this->assertTrue($service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA'
                ]
            ],
            'from' => $source['start']->id,
            'to' => $source['a']->id,
        ])->ok);

        $this->expectException(ValidationException::class);
        $service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'EqualEdge'
                ]
            ],
            'from' => $source['a']->id,
            'to' => $source['a']->id,
        ]);
    }

    /**
     * Store: "to" cannot point to START.
     * @throws Throwable
     */
    public function test_store_rejects_to_pointing_to_start(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX4');

        $this->assertTrue($service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA'
                ]
            ],
            'from' => $source['start']->id,
            'to' => $source['a']->id,
        ])->ok);

        $this->expectException(ValidationException::class);
        $service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'BadToStart'
                ]
            ],
            'from' => $source['a']->id,
            'to' => $source['start']->id,
        ]);
    }

    /**
     * Store: duplicate (flow_id, from, to) pair must fail.
     * @throws Throwable
     */
    public function test_store_duplicate_pair_fails(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX5');

        $this->assertTrue($service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA'
                ]
            ],
            'from' => $source['start']->id,
            'to' => $source['a']->id,
        ])->ok);

        $this->expectException(ValidationException::class);
        $service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'Dup'
                ]
            ],
            'from' => $source['start']->id,
            'to' => $source['a']->id,
        ]);
    }

    /**
     * Store: duplicate translated "name" in same flow should fail.
     * @throws Throwable
     */
    public function test_store_duplicate_translation_name_in_same_flow_fails(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX6');

        $this->assertTrue($service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'UniqueName'
                ]
            ],
            'from' => $source['start']->id,
            'to' => $source['a']->id,
        ])->ok);

        $this->expectException(ValidationException::class);
        $service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'UniqueName'
                ]
            ],
            'from' => $source['a']->id,
            'to' => $source['b']->id,
        ]);
    }

    /**
     * Update: can change slug/name; reject from==to.
     * @throws Throwable
     */
    public function test_update_changes_fields_and_rejects_equal_endpoints(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX7');

        $service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA'
                ]
            ],
            'from' => $source['start']->id,
            'to' => $source['a']->id,
            'slug' => 's-a',
        ])->data->resource;

        $tAB = $service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'AToB'
                ]
            ],
            'from' => $source['a']->id,
            'to' => $source['b']->id,
            'slug' => 'a-b',
        ])->data->resource;

        $upd = $service->update($tAB->id, [
            'translation' => [
                'en' => [
                    'name' => 'AToB v2'
                ]
            ],
            'slug' => 'a-b-v2',
        ]);
        $this->assertTrue($upd->ok);

        $this->expectException(ValidationException::class);
        $service->update($tAB->id, [
            'from' => $source['a']->id,
            'to' => $source['a']->id,
        ]);
    }

    /**
     * Update: duplicate (flow_id, from, to) after update rejected.
     * @throws Throwable
     */
    public function test_update_duplicate_pair_rejected(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX8');

        $service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA'
                ]
            ],
            'from' => $source['start']->id,
            'to' => $source['a']->id,
        ])->data->resource;

        $tAB = $service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'AToB'
                ]
            ],
            'from' => $source['a']->id,
            'to' => $source['b']->id,
        ])->data->resource;

        $tBA = $service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'BToA'
                ]
            ],
            'from' => $source['b']->id,
            'to' => $source['a']->id,
        ])->data->resource;

        $this->expectException(ValidationException::class);
        $service->update($tBA->id, [
            'from' => $source['a']->id,
            'to' => $source['b']->id, // collides with existing A->B
        ]);
    }

    /**
     * Update: cannot set "to" as START.
     * @throws Throwable
     */
    public function test_update_cannot_set_to_start(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX9');

        $t = $service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA'
                ]
            ],
            'from' => $source['start']->id,
            'to' => $source['a']->id,
        ])->data->resource;

        $this->expectException(ValidationException::class);
        $service->update($t->id, [
            'to' => $source['start']->id,
        ]);
    }

    /**
     * Destroy: cannot delete START→* if another START→* exists.
     * @throws Throwable
     */
    public function test_destroy_rejects_deleting_non_last_start_transition(): void
    {
        $service = app(FlowTransitionService::class);
        $source = $this->makeFlowWithStartAndTwoStates('TX10');

        $tSA = $service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'StartToA'
                ]
            ],
            'from' => $source['start']->id,
            'to' => $source['a']->id,
        ])->data->resource;

        $tSB = $service->store([
            'flow_id' => $source['flow']->id,
            'translation' => [
                'en' => [
                    'name' => 'AToB'
                ]
            ],
            'from' => $source['a']->id,
            'to' => $source['b']->id,
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
            'flow_id' => $flow->id,
            'translation' => [
                'en' => [
                    'name' => 'A',
                    'description' => null
                ]
            ],
            'status' => OrderStatusEnum::PENDING(),
        ])->data->resource;

        $b = $stateService->store([
            'flow_id' => $flow->id,
            'translation' => [
                'en' => [
                    'name' => 'B',
                    'description' => null
                ]
            ],
            'status' => OrderStatusEnum::PENDING(),
        ])->data->resource;

        $startEdge = FlowTransitionModel::query()->create([
            'flow_id' => $flow->id,
            'from' => null,
            'to' => $start->id,
            'slug' => null,
        ]);

        $normal = FlowTransitionModel::query()->create([
            'flow_id' => $flow->id,
            'from' => $a->id,
            'to' => $b->id,
            'slug' => 'a-b',
        ]);

        $endEdge = FlowTransitionModel::query()->create([
            'flow_id' => $flow->id,
            'from' => $b->id,
            'to' => null,
            'slug' => null,
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
            'flow_id' => $flow->id,
            'translation' => [
                'en' => [
                    'name' => 'A',
                    'description' => null
                ]
            ],
            'status' => OrderStatusEnum::PENDING(),
        ])->data->resource;

        $b = $stateService->store([
            'flow_id' => $flow->id,
            'translation' => [
                'en' => [
                    'name' => 'B',
                    'description' => null
                ]
            ],
            'status' => OrderStatusEnum::PENDING(),
        ])->data->resource;

        $t = FlowTransitionModel::query()
            ->create([
                'flow_id' => $flow->id,
                'from' => $a->id,
                'to' => $b->id,
                'slug' => 'a-b',
            ])
            ->load(['flow', 'fromState', 'toState', 'tasks', 'instances']);

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
}
