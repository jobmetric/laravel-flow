<?php

namespace JobMetric\Flow\Http\Controllers;

use JobMetric\Flow\Facades\Flow as FlowFacade;
use JobMetric\Flow\Http\Controllers\Controller as BaseFlowController;
use JobMetric\Flow\Http\Requests\Flow\StoreFlowRequest;
use JobMetric\Flow\Http\Requests\Flow\UpdateFlowRequest;
use JobMetric\Flow\Http\Resources\FlowResource;

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
    public function store(StoreFlowRequest $request): FlowResource
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
    public function show(int $flow)
    {
        return FlowResource::make(
            FlowFacade::show($flow)
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

    /**
     * Restore the specified resource from storage.
     */
    public function restore(int $flow)
    {
        return FlowResource::make(
            FlowFacade::restore($flow)
        );
    }

    /**
     * Restore the specified resource from storage.
     */
    public function forceDelete(int $flow)
    {
        return FlowResource::make(
            FlowFacade::forceDelete($flow)
        );
    }
}
