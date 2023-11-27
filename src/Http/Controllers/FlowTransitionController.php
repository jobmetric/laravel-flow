<?php

namespace JobMetric\Flow\Http\Controllers;

use JobMetric\Flow\Facades\FlowTransition as FlowTransitionFacade;
use JobMetric\Flow\Http\Controllers\Controller as BaseFlowController;
use JobMetric\Flow\Http\Requests\FlowTransition\StoreFlowTransitionRequest;
use JobMetric\Flow\Http\Requests\FlowTransition\UpdateFlowTransitionRequest;
use JobMetric\Flow\Http\Resources\FlowTransitionResource;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowTransition;

class FlowTransitionController extends BaseFlowController
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
     * @param StoreFlowTransitionRequest $request
     *
     * @return FlowTransitionResource
     */
    public function store(Flow $flow, StoreFlowTransitionRequest $request): FlowTransitionResource
    {
        return FlowTransitionResource::make(
            FlowTransitionFacade::store(
                $flow->id,
                $request->validated()
            )
        );
    }

    /**
     * Display the specified resource.
     *
     * @param Flow $flow
     * @param FlowTransition $flow_transition
     *
     * @return FlowTransitionResource
     */
    public function show(Flow $flow, FlowTransition $flow_transition): FlowTransitionResource
    {
        return FlowTransitionResource::make($flow_transition->load(['flow', 'fromState', 'toState']));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Flow $flow
     * @param FlowTransition $flow_transition
     * @param UpdateFlowTransitionRequest $request
     *
     * @return FlowTransitionResource
     */
    public function update(Flow $flow, FlowTransition $flow_transition, UpdateFlowTransitionRequest $request): FlowTransitionResource
    {
        return FlowTransitionResource::make(
            FlowTransitionFacade::update($flow_transition->id, $request->validated())
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Flow $flow
     * @param FlowTransition $flowTransition
     *
     * @return FlowTransitionResource
     */
    public function destroy(Flow $flow, FlowTransition $flowTransition): FlowTransitionResource
    {
        return FlowTransitionResource::make(
            FlowTransitionFacade::delete($flowTransition->id)
        );
    }
}
