<?php

namespace JobMetric\Flow\Http\Controllers;

use JobMetric\Flow\Http\Controllers\Controller as BaseFlowController;
use JobMetric\Flow\Http\Requests\FlowState\StoreFlowStateRequest;
use JobMetric\Flow\Http\Requests\FlowState\UpdateFlowStateRequest;
use JobMetric\Flow\Facades\FlowState as FlowStateFacade;
use JobMetric\Flow\Http\Resources\FlowStateResource;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowState;

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
     *
     * @param Flow $flow
     * @param StoreFlowStateRequest $request
     *
     * @return FlowStateResource
     */
    public function store(Flow $flow, StoreFlowStateRequest $request): FlowStateResource
    {
        return FlowStateResource::make(
            FlowStateFacade::store(
                $flow->id,
                $request->validated()
            )
        );
    }

    /**
     * Display the specified resource.
     *
     * @param Flow $flow
     * @param FlowState $flow_state
     *
     * @return FlowStateResource
     */
    public function show(Flow $flow, FlowState $flow_state): FlowStateResource
    {
        return FlowStateResource::make($flow_state->load('flow'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Flow $flow
     * @param FlowState $flow_state
     * @param UpdateFlowStateRequest $request
     *
     * @return FlowStateResource
     */
    public function update(Flow $flow, FlowState $flow_state, UpdateFlowStateRequest $request): FlowStateResource
    {
        return FlowStateResource::make(
            FlowStateFacade::update($flow_state->id, $request->validated())
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Flow $flow
     * @param FlowState $flow_state
     *
     * @return FlowStateResource
     */
    public function destroy(Flow $flow, FlowState $flow_state): FlowStateResource
    {
        return FlowStateResource::make(
            FlowStateFacade::delete($flow_state->id)
        );
    }
}
