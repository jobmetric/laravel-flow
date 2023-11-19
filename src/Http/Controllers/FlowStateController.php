<?php

namespace JobMetric\Flow\Http\Controllers;

use JobMetric\Flow\Http\Controllers\Controller as BaseFlowController;
use JobMetric\Flow\Http\Requests\FlowState\StoreFlowStateRequest;
use JobMetric\Flow\Http\Requests\FlowState\UpdateFlowStateRequest;
use JobMetric\Flow\Http\Resources\FlowResource;
use JobMetric\Flow\Facades\FlowState as FlowStateFacade;
use JobMetric\Flow\Http\Resources\FlowStateResource;

class FlowStateController extends BaseFlowController
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
     */
    public function store(int $flow, StoreFlowStateRequest $request): FlowStateResource
    {
        return FlowStateResource::make(
            FlowStateFacade::store(
                $flow,
                $request->validated()
            )
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(int $flow_state): FlowStateResource
    {
        return FlowStateResource::make(
            FlowStateFacade::show($flow_state, ['flow'])
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFlowRequest $request, int $flow)
    {
        return FlowResource::make(
            FlowFacade::update(
                $flow,
                $request->validated()
            )
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $flow)
    {
        return FlowResource::make(
            FlowFacade::delete($flow)
        );
    }
}
