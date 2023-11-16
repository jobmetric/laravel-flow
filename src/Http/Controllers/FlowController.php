<?php

namespace JobMetric\Flow\Http\Controllers;

use JobMetric\Flow\Http\Controllers\Controller as BaseFlowController;
use JobMetric\Flow\Http\Requests\StoreFlowRequest;
use JobMetric\Flow\Http\Requests\UpdateFlowRequest;
use JobMetric\Flow\Http\Resources\FlowResource;
use JobMetric\Flow\Models\Flow;
use \JobMetric\Flow\Facades\Flow as FlowFacade;

class FlowController extends BaseFlowController
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
    public function store(StoreFlowRequest $request)
    {
        return FlowResource::make(
            FlowFacade::store(
                $request->validated()
            )
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Flow $flow)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFlowRequest $request, Flow $flow)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Flow $flow)
    {
        //
    }
}
