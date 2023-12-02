<?php

namespace JobMetric\Flow\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use JobMetric\Flow\Facades\Flow as FlowFacade;
use JobMetric\Flow\Facades\FlowTask;
use JobMetric\Flow\Http\Controllers\Controller as BaseFlowController;
use JobMetric\Flow\Http\Requests\Flow\StoreFlowTaskRequest;
use JobMetric\Flow\Http\Requests\Flow\UpdateFlowTaskRequest;
use JobMetric\Flow\Http\Resources\FlowResource;
use JobMetric\Flow\Http\Resources\FlowTaskDetailResource;
use JobMetric\Flow\Http\Resources\FlowTaskDriversResource;
use JobMetric\Flow\Models\Flow;

class FlowTaskController extends BaseFlowController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreFlowTaskRequest $request
     *
     * @return FlowResource
     */
    public function store(StoreFlowTaskRequest $request): FlowResource
    {

    }

    /**
     * Display the specified resource.
     *
     * @param Flow $flow
     *
     * @return FlowResource
     */
    public function show(Flow $flow): FlowResource
    {
        return FlowResource::make($flow);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Flow $flow
     * @param UpdateFlowTaskRequest $request
     *
     * @return FlowResource
     */
    public function update(Flow $flow, UpdateFlowTaskRequest $request): FlowResource
    {
        return FlowResource::make(
            FlowFacade::update($flow->id, $request->validated())
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Flow $flow
     *
     * @return FlowResource
     */
    public function destroy(Flow $flow): FlowResource
    {
        return FlowResource::make(
            FlowFacade::delete($flow->id)
        );
    }

    /**
     * Restore the specified resource from storage.
     *
     * @param Flow $flow
     *
     * @return FlowResource
     */
    public function restore(Flow $flow): FlowResource
    {
        return FlowResource::make(
            FlowFacade::restore($flow->id)
        );
    }

    /**
     * Restore the specified resource from storage.
     *
     * @param Flow $flow
     *
     * @return FlowResource
     */
    public function forceDelete(Flow $flow)
    {
        return FlowResource::make(
            FlowFacade::forceDelete($flow->id)
        );
    }

    /**
     * get all task driver
     *
     * @param Flow $flow
     *
     * @return AnonymousResourceCollection
     */
    public function drivers(Flow $flow): AnonymousResourceCollection
    {
        return FlowTaskDriversResource::collection(
            FlowTask::drivers($flow->driver)
        );
    }

    /**
     * get details task driver
     *
     * @param string $flow_driver
     * @param string $task_driver
     *
     * @return JsonResource
     */
    public function details(string $flow_driver, string $task_driver): JsonResource
    {
        return FlowTaskDetailResource::make(
            FlowTask::details($flow_driver, $task_driver)
        );
    }
}
