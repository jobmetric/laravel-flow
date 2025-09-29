<?php

namespace JobMetric\Flow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use JobMetric\Flow\Http\Resources\FlowTransitionResource;
use JobMetric\Flow\Models\FlowTransition as FlowTransitionModel;
use JobMetric\PackageCore\Output\Response;
use JobMetric\PackageCore\Services\AbstractCrudService;
use Throwable;

class FlowTransition extends AbstractCrudService
{
    use InvalidatesFlowCache;

    /**
     * Translation key for entity name used in responses.
     *
     * @var string
     */
    protected string $entityName = 'workflow::base.entity_names.flow_transition';

    /**
     * Bound Eloquent model.
     *
     * @var class-string
     */
    protected static string $modelClass = FlowTransitionModel::class;

    /**
     * Bound API Resource.
     *
     * @var class-string
     */
    protected static string $resourceClass = FlowTransitionResource::class;

    /**
     * Allowed fields for query builder (select/filter/sort).
     *
     * @var string[]
     */
    protected static array $fields = [
        'id',
        'flow_id',
        'from',
        'to',
        'slug',
        'created_at',
        'updated_at',
    ];

    /**
     * Create a new transition after enforcing invariants.
     *
     * @param array $data
     * @param array $with
     *
     * @return Response
     * @throws Throwable
     */
    public function doStore(array $data, array $with = []): Response
    {
        $normalized = DB::transaction(function () use ($data) {
            return app(Pipeline::class)
                ->send($data)
                ->through([

                ])
                ->thenReturn();
        });

        return parent::store($normalized, $with);
    }

    /**
     * Update a transition after enforcing invariants.
     *
     * @param int $id
     * @param array $data
     * @param array $with
     *
     * @return Response
     * @throws Throwable
     */
    public function doUpdate(int $id, array $data, array $with = []): Response
    {
        $normalized = DB::transaction(function () use ($id, $data) {
            return app(Pipeline::class)
                ->send([
                    'id' => $id,
                    ...Arr::except($data, ['id']),
                ])
                ->through([

                ])
                ->thenReturn();
        });

        return parent::update($id, $normalized, $with);
    }

    /**
     * Delete a transition after enforcing invariants.
     *
     * @param int $id
     * @param array $with
     *
     * @return Response
     * @throws Throwable
     */
    public function doDestroy(int $id, array $with = []): Response
    {
        return parent::destroy($id, $with);
    }

    /**
     * Runs right after model is persisted (create).
     *
     * @param Model $model
     * @param array $data
     *
     * @return void
     */
    protected function afterStore(Model $model, array &$data): void
    {
        $this->forgetCache();
    }

    /**
     * Runs right after model is persisted (update).
     *
     * @param Model $model
     * @param array $data
     *
     * @return void
     */
    protected function afterUpdate(Model $model, array &$data): void
    {
        $this->forgetCache();
    }

    /**
     * Runs right after deletion.
     *
     * @param Model $model
     *
     * @return void
     */
    protected function afterDestroy(Model $model): void
    {
        $this->forgetCache();
    }
}
