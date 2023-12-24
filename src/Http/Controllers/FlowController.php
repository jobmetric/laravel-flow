<?php

namespace JobMetric\Flow\Http\Controllers;

use JobMetric\Flow\Facades\Flow as FlowFacade;
use JobMetric\Flow\Http\Controllers\Controller as BaseFlowController;
use JobMetric\Flow\Http\Requests\Flow\StoreFlowRequest;
use JobMetric\Flow\Http\Requests\Flow\UpdateFlowRequest;
use JobMetric\Flow\Http\Resources\FlowResource;
use JobMetric\Flow\Models\Flow;

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
     *
     * @param StoreFlowRequest $request
     *
     * @return FlowResource
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
     * @param Flow                  $flow
     * @param UpdateFlowRequest $request
     *
     * @return FlowResource
     */
    public function update(Flow $flow, UpdateFlowRequest $request): FlowResource
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
    public function forceDelete(Flow $flow): FlowResource
    {
        return FlowResource::make(
            FlowFacade::forceDelete($flow->id)
        );
    }
}
