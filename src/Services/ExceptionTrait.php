<?php

namespace JobMetric\Flow\Services;

use Illuminate\Database\Eloquent\Builder;
use JobMetric\Flow\Enums\TableFlowStateFieldTypeEnum;
use JobMetric\Flow\Exceptions\FlowInactiveException;
use JobMetric\Flow\Exceptions\FlowTransitionExistException;
use JobMetric\Flow\Exceptions\FlowTransitionFromNotSetException;
use JobMetric\Flow\Exceptions\FlowTransitionFromStateStartNotMoveException;
use JobMetric\Flow\Exceptions\FlowTransitionHaveAtLeastOneTransitionFromTheStartBeginningException;
use JobMetric\Flow\Exceptions\FlowTransitionInvalidException;
use JobMetric\Flow\Exceptions\FlowTransitionNotStoreBeforeFirstStateException;
use JobMetric\Flow\Exceptions\FlowTransitionNotStoreBeforeFirstTransitionException;
use JobMetric\Flow\Exceptions\FlowTransitionSlugExistException;
use JobMetric\Flow\Exceptions\FlowTransitionStateDriverFromAndToNotEqualException;
use JobMetric\Flow\Exceptions\FlowTransitionStateEndNotInFromException;
use JobMetric\Flow\Exceptions\FlowTransitionStateStartNotInToException;
use JobMetric\Flow\Exceptions\FlowTransitionToNotSetException;
use JobMetric\Flow\Facades\Flow as FlowFacade;
use JobMetric\Flow\Facades\FlowState as FlowStateFacade;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowTransition;

trait ExceptionTrait
{
    /**
     * check flow inactive
     *
     * @param Flow $flow
     *
     * @return void
     * @throws FlowInactiveException
     */
    private function checkFlowInactive(Flow $flow): void
    {
        if (!$flow->status) {
            throw new FlowInactiveException($flow->driver);
        }
    }

    /**
     * check slug exist
     *
     * @param Flow $flow
     * @param array $data
     * @param int|null $updated_flow_transition_id
     *
     * @return void
     * @throws FlowTransitionSlugExistException
     */
    private function checkSlugExist(Flow $flow, array $data, int|null $updated_flow_transition_id = null): void
    {
        if (array_key_exists('slug', $data)) {
            if (is_null($data['slug'])) {
                return;
            }

            $query = FlowTransition::ofSlug($data['slug'])
                ->whereHas('flow', function (Builder $q) use ($flow) {
                    $q->where('driver', $flow->driver);
                });

            if ($updated_flow_transition_id) {
                $query->where('id', '<>', $updated_flow_transition_id);
            }

            if ($query->exists()) {
                throw new FlowTransitionSlugExistException($data['slug']);
            }
        }
    }

    /**
     * check from exist
     *
     * @param array $data
     *
     * @return void
     * @throws FlowTransitionFromNotSetException
     */
    private function checkFromExist(array $data): void
    {
        if (!array_key_exists('from', $data)) {
            throw new FlowTransitionFromNotSetException;
        }
    }

    /**
     * check to exist
     *
     * @param array $data
     *
     * @return void
     * @throws FlowTransitionToNotSetException
     */
    private function checkToExist(array $data): void
    {
        if (!array_key_exists('to', $data)) {
            throw new FlowTransitionToNotSetException;
        }
    }

    /**
     * check from and to exist
     *
     * @param array $data
     *
     * @return void
     * @throws FlowTransitionInvalidException
     */
    private function checkFromAndToExist(array $data): void
    {
        if (!array_key_exists('from', $data) && !array_key_exists('to', $data)) {
            throw new FlowTransitionInvalidException;
        }
    }

    /**
     * check from and to is equal
     *
     * @param array $data
     *
     * @return void
     * @throws FlowTransitionInvalidException
     */
    private function checkFromAndToIsEqual(array $data): void
    {
        if (array_key_exists('from', $data) && array_key_exists('to', $data) && $data['from'] == $data['to']) {
            throw new FlowTransitionInvalidException;
        }
    }

    /**
     * check state end not in from
     *
     * @param array $data
     *
     * @return void
     * @throws FlowTransitionStateEndNotInFromException
     */
    private function checkStateEndNotInFrom(array $data): void
    {
        if (array_key_exists('from', $data)) {
            $stateFrom = FlowStateFacade::show($data['from']);

            if ($stateFrom?->type == TableFlowStateFieldTypeEnum::END) {
                throw new FlowTransitionStateEndNotInFromException;
            }
        }
    }

    /**
     * check state start not in to
     *
     * @param array $data
     *
     * @return void
     * @throws FlowTransitionStateStartNotInToException
     */
    private function checkStateStartNotInTo(array $data): void
    {
        if (array_key_exists('to', $data)) {
            $stateTo = FlowStateFacade::show($data['to']);

            if ($stateTo?->type == TableFlowStateFieldTypeEnum::START) {
                throw new FlowTransitionStateStartNotInToException;
            }
        }
    }

    /**
     * check state driver from and to is not equal
     *
     * @param array $data
     *
     * @return void
     * @throws FlowTransitionStateDriverFromAndToNotEqualException
     */
    private function checkStateDriverFromAndToNotEqual(array $data): void
    {
        if (array_key_exists('from', $data) && array_key_exists('to', $data)) {
            $stateFrom = FlowStateFacade::show($data['from']);
            $stateTo = FlowStateFacade::show($data['to']);

            if ($stateFrom && $stateTo) {
                if ($stateFrom->flow->driver != $stateTo->flow->driver) {
                    throw new FlowTransitionStateDriverFromAndToNotEqualException;
                }
            }
        }
    }

    /**
     * check transition exist
     *
     * @param array $data
     * @param int|null $updated_flow_transition_id
     *
     * @return void
     * @throws FlowTransitionExistException
     */
    private function checkTransitionExist(array $data, int|null $updated_flow_transition_id = null): void
    {
        $query = FlowTransition::ofFrom($data['from'])->ofTo($data['to']);

        if ($updated_flow_transition_id) {
            $query->where('id', '<>', $updated_flow_transition_id);
        }

        $check = $query->exists();

        if ($check) {
            throw new FlowTransitionExistException;
        }
    }

    /**
     * check from state start not move
     *
     * @param FlowTransition $flowTransition
     * @param array $data
     *
     * @return void
     * @throws FlowTransitionFromStateStartNotMoveException
     */
    private function checkFromStateStartNotMove(FlowTransition $flowTransition, array $data): void
    {
        $flowTransition->load(['fromState']);

        if ($flowTransition?->fromState?->type === TableFlowStateFieldTypeEnum::START && $flowTransition?->fromState?->id != $data['from']) {
            throw new FlowTransitionFromStateStartNotMoveException;
        }
    }

    /**
     * check not store before first transition
     *
     * @param Flow $flow
     * @param array $data
     *
     * @return void
     * @throws FlowTransitionNotStoreBeforeFirstStateException
     */
    private function checkNotStoreBeforeFirstTransition(Flow $flow, array $data): void
    {
        $startState = FlowFacade::getStartState($flow->id);

        if ($flow->transitions()->count() == 0) {
            if ($data['from'] != $startState?->id) {
                throw new FlowTransitionNotStoreBeforeFirstStateException;
            }
        }
    }

    /**
     * check have at least one transition from the start beginning
     *
     * @param FlowTransition $flowTransition
     *
     * @return void
     * @throws FlowTransitionHaveAtLeastOneTransitionFromTheStartBeginningException
     */
    private function checkTransitionHaveAtLeastOneTransitionFromTheStartBeginning(FlowTransition $flowTransition): void
    {
        $startState = FlowFacade::getStartState($flowTransition->flow->id);

        $countTransitionWithStartState = FlowTransition::query()->where([
            'flow_id' => $flowTransition->flow->id,
            'from' => $startState->id
        ])->count();

        if (1 == $countTransitionWithStartState) {
            throw new FlowTransitionHaveAtLeastOneTransitionFromTheStartBeginningException;
        }
    }
}
