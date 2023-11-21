<?php

namespace JobMetric\Flow\Services\FlowState;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use JobMetric\Flow\Enums\TableFlowStateFieldTypeEnum;
use JobMetric\Flow\Events\FlowState\FlowStateStoreEvent;
use JobMetric\Flow\Events\FlowState\FlowStateUpdateEvent;
use JobMetric\Flow\Exceptions\FlowInactiveException;
use JobMetric\Flow\Exceptions\FlowStateInvalidTypeException;
use JobMetric\Flow\Exceptions\FlowStateStartTypeIsExistException;
use JobMetric\Flow\Exceptions\FlowStatusInvalidException;
use JobMetric\Flow\Facades\Flow;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Metadata\JMetadata;

class FlowStateManager
{
    /**
     * The application instance.
     *
     * @var Application
     */
    protected Application $app;

    /**
     * The metadata instance.
     *
     * @var JMetadata
     */
    protected JMetadata $JMetadata;

    /**
     * Create a new Translation instance.
     *
     * @param Application $app
     *
     * @return void
     * @throws BindingResolutionException
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->JMetadata = $app->make('JMetadata');
    }

    /**
     * store flow state
     *
     * @param int $flow_id
     * @param array $data
     *
     * @return FlowState
     * @throws FlowInactiveException
     * @throws FlowStateStartTypeIsExistException
     */
    public function store(int $flow_id, array $data): FlowState
    {
        $flow = Flow::show($flow_id);

        if(!$flow->status) {
            throw new FlowInactiveException($flow->driver);
        }

        if($data['type'] == TableFlowStateFieldTypeEnum::START()) {
            throw new FlowStateStartTypeIsExistException($flow->driver);
        }

        $flowState = $flow->states()->create([
            'type' => $data['type'],
            'config' => [
                'color' => $data['color'],
                'position' => $data['position'],
            ],
            'status' => $data['status']
        ]);

        event(new FlowStateStoreEvent($flowState));

        return $flowState;
    }

    /**
     * show flow state
     *
     * @param int $flow_state_id
     * @param array $with
     *
     * @return FlowState
     */
    public function show(int $flow_state_id, array $with = []): FlowState
    {
        return FlowState::findOrFail($flow_state_id)->load($with);
    }

    /**
     * update flow state
     *
     * @param int $flow_state_id
     * @param array $data
     *
     * @return FlowState
     * @throws FlowStateInvalidTypeException
     * @throws FlowStatusInvalidException
     */
    public function update(int $flow_state_id, array $data = []): FlowState
    {
        $flowState = $this->show($flow_state_id);

        if(isset($data['type'])) {
            if(!in_array($data['type'], array_diff(TableFlowStateFieldTypeEnum::values(), [TableFlowStateFieldTypeEnum::START()]))) {
                throw new FlowStateInvalidTypeException($data['type']);
            }

            $flowState->type = $data['type'];
        }

        if(isset($data['color'])) {
            $flowState->config = array_merge($flowState->config ?? [], [
                'color' => $data['color']
            ]);
        }

        if(isset($data['position'])) {
            $flowState->config = array_merge($flowState->config ?? [], [
                'position' => $data['position']
            ]);
        }

        if(isset($data['status'])) {
            if (!in_array($data['status'], flowGetStatus($flowState->flow->driver))) {
                throw new FlowStatusInvalidException(flowGetStatus($flowState->flow->driver));
            }

            $flowState->status = $data['status'];
        }

        $flowState->save();

        event(new FlowStateUpdateEvent($flowState, $data));

        return $flowState;
    }
}
