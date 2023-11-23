<?php

namespace JobMetric\Flow\Services\FlowTransition;

use JobMetric\Flow\Enums\TableFlowStateFieldTypeEnum;
use JobMetric\Flow\Exceptions\FlowInactiveException;
use JobMetric\Flow\Exceptions\FlowTransitionExistException;
use JobMetric\Flow\Exceptions\FlowTransitionFromNotSetException;
use JobMetric\Flow\Exceptions\FlowTransitionInvalidException;
use JobMetric\Flow\Exceptions\FlowTransitionSlugExistException;
use JobMetric\Flow\Exceptions\FlowTransitionStateDriverFromAndToNotEqualException;
use JobMetric\Flow\Exceptions\FlowTransitionStateEndNotInFromException;
use JobMetric\Flow\Exceptions\FlowTransitionStateStartNotInToException;
use JobMetric\Flow\Exceptions\FlowTransitionToNotSetException;
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
     *
     * @return void
     * @throws FlowTransitionSlugExistException
     */
    private function checkSlugExist(Flow $flow, array $data): void
    {
        if (isset($data['slug'])) {
            if (FlowTransition::ofSlug($data['slug'])->exists()) {
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
        if (!isset($data['from'])) {
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
        if (!isset($data['to'])) {
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
        if (!$data['from'] && !$data['to']) {
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
        if ($data['from'] && $data['to'] && $data['from'] == $data['to']) {
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
        if ($data['from']) {
            $stateFrom = FlowStateFacade::show($data['from']);

            if ($stateFrom->type == TableFlowStateFieldTypeEnum::END) {
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
        if ($data['to']) {
            $stateTo = FlowStateFacade::show($data['to']);

            if ($stateTo->type == TableFlowStateFieldTypeEnum::START) {
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
        if ($data['from'] && $data['to']) {
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
     *
     * @return void
     * @throws FlowTransitionExistException
     */
    private function checkTransitionExist(array $data): void
    {
        $check = FlowTransition::ofFrom($data['from'])->ofTo($data['to'])->exists();

        if ($check) {
            throw new FlowTransitionExistException;
        }
    }
}
