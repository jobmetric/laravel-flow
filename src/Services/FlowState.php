<?php

namespace JobMetric\Flow\Services;

use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Flow\Events\FlowState\FlowStateDeleteEvent;
use JobMetric\Flow\Events\FlowState\FlowStateStoreEvent;
use JobMetric\Flow\Events\FlowState\FlowStateUpdateEvent;
use JobMetric\Flow\Exceptions\Old\FlowInactiveException;
use JobMetric\Flow\Exceptions\Old\FlowStateInvalidTypeException;
use JobMetric\Flow\Exceptions\Old\FlowStateStartTypeIsExistException;
use JobMetric\Flow\Exceptions\Old\FlowStateStartTypeIsNotChangeException;
use JobMetric\Flow\Exceptions\Old\FlowStateStartTypeIsNotDeleteException;
use JobMetric\Flow\Exceptions\Old\FlowStatusInvalidException;
use JobMetric\Flow\Facades\Flow;
use JobMetric\Flow\Models\FlowState as FlowStateModel;
use JobMetric\Metadata\Metadata;

class FlowState
{
    /**
     * store flow state
     *
     * @param int $flow_id
     * @param array $data
     *
     * @return FlowStateModel
     * @throws \JobMetric\Flow\Exceptions\Old\FlowInactiveException
     * @throws \JobMetric\Flow\Exceptions\Old\FlowStateStartTypeIsExistException
     */
    public function store(int $flow_id, array $data): FlowStateModel
    {
        $flow = Flow::show($flow_id);

        if (!$flow->status) {
            throw new FlowInactiveException($flow->driver);
        }

        if ($data['type'] == FlowStateTypeEnum::START()) {
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
     * @param int|null $flow_state_id
     * @param array $with
     *
     * @return ?FlowStateModel
     */
    public function show(int|null $flow_state_id, array $with = []): ?FlowStateModel
    {
        if(is_null($flow_state_id)) {
            return null;
        }

        return FlowStateModel::findOrFail($flow_state_id)->load($with);
    }

    /**
     * update flow state
     *
     * @param int $flow_state_id
     * @param array $data
     *
     * @return FlowStateModel
     * @throws \JobMetric\Flow\Exceptions\Old\FlowStateInvalidTypeException
     * @throws \JobMetric\Flow\Exceptions\Old\FlowStatusInvalidException
     * @throws \JobMetric\Flow\Exceptions\Old\FlowStateStartTypeIsNotChangeException
     */
    public function update(int $flow_state_id, array $data = []): FlowStateModel
    {
        $flowState = $this->show($flow_state_id);

        if (isset($data['type'])) {
            if (!in_array($data['type'], array_diff(FlowStateTypeEnum::values(), [FlowStateTypeEnum::START()]))) {
                throw new FlowStateInvalidTypeException($data['type']);
            }

            if($flowState->type == FlowStateTypeEnum::START() && $data['type'] != FlowStateTypeEnum::START()) {
                throw new FlowStateStartTypeIsNotChangeException($flowState->flow->driver);
            } else {
                $flowState->type = $data['type'];
            }
        }

        if (isset($data['color'])) {
            $flowState->config = array_merge($flowState->config ?? [], [
                'color' => $data['color']
            ]);
        }

        if (isset($data['position'])) {
            $flowState->config = array_merge($flowState->config ?? [], [
                'position' => $data['position']
            ]);
        }

        if (isset($data['status'])) {
            if (!in_array($data['status'], flowGetStatus($flowState->flow->driver))) {
                throw new FlowStatusInvalidException(flowGetStatus($flowState->flow->driver));
            }

            $flowState->status = $data['status'];
        }

        $flowState->save();

        event(new FlowStateUpdateEvent($flowState, $data));

        return $flowState;
    }

    /**
     * delete flow state
     *
     * @param int $flow_state_id
     *
     * @return FlowStateModel
     * @throws FlowStateStartTypeIsNotDeleteException
     */
    public function delete(int $flow_state_id): FlowStateModel
    {
        // todo: check dependencies

        $flow_state = $this->show($flow_state_id);

        if($flow_state->type == FlowStateTypeEnum::START) {
            throw new FlowStateStartTypeIsNotDeleteException;
        }

        $flow_state->delete();

        event(new FlowStateDeleteEvent($flow_state));

        return $flow_state;
    }
}
