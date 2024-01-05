<?php

namespace JobMetric\Flow\Services;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use JobMetric\Flow\Events\FlowTransition\FlowTransitionDeleteEvent;
use JobMetric\Flow\Events\FlowTransition\FlowTransitionStoreEvent;
use JobMetric\Flow\Events\FlowTransition\FlowTransitionUpdateEvent;
use JobMetric\Flow\Exceptions\FlowInactiveException;
use JobMetric\Flow\Exceptions\FlowTransitionExistException;
use JobMetric\Flow\Exceptions\FlowTransitionFromNotSetException;
use JobMetric\Flow\Exceptions\FlowTransitionFromStateStartNotMoveException;
use JobMetric\Flow\Exceptions\FlowTransitionHaveAtLeastOneTransitionFromTheStartBeginningException;
use JobMetric\Flow\Exceptions\FlowTransitionInvalidException;
use JobMetric\Flow\Exceptions\FlowTransitionNotStoreBeforeFirstStateException;
use JobMetric\Flow\Exceptions\FlowTransitionSlugExistException;
use JobMetric\Flow\Exceptions\FlowTransitionStateDriverFromAndToNotEqualException;
use JobMetric\Flow\Exceptions\FlowTransitionStateEndNotInFromException;
use JobMetric\Flow\Exceptions\FlowTransitionStateStartNotInToException;
use JobMetric\Flow\Exceptions\FlowTransitionToNotSetException;
use JobMetric\Flow\Facades\Flow;
use JobMetric\Flow\Models\FlowTransition;
use JobMetric\Metadata\Metadata;

class FlowTransitionManager
{
    use ExceptionTrait;

    /**
     * The application instance.
     *
     * @var Application
     */
    protected Application $app;

    /**
     * The metadata instance.
     *
     * @var Metadata
     */
    protected Metadata $metadata;

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

        $this->Metadata = $app->make('Metadata');
    }

    /**
     * store flow transition
     *
     * @param int $flow_id
     * @param array $data
     *
     * @return FlowTransition
     * @throws FlowInactiveException
     * @throws FlowTransitionSlugExistException
     * @throws FlowTransitionInvalidException
     * @throws FlowTransitionStateEndNotInFromException
     * @throws FlowTransitionFromNotSetException
     * @throws FlowTransitionToNotSetException
     * @throws FlowTransitionStateStartNotInToException
     * @throws FlowTransitionStateDriverFromAndToNotEqualException
     * @throws FlowTransitionExistException
     * @throws FlowTransitionNotStoreBeforeFirstStateException
     */
    public function store(int $flow_id, array $data): FlowTransition
    {
        $flow = Flow::show($flow_id);

        // add checker
        $this->checkFlowInactive($flow);
        $this->checkSlugExist($flow, $data);
        $this->checkFromExist($data);
        $this->checkToExist($data);
        $this->checkFromAndToExist($data);
        $this->checkFromAndToIsEqual($data);
        $this->checkStateEndNotInFrom($data);
        $this->checkStateStartNotInTo($data);
        $this->checkStateDriverFromAndToNotEqual($data);
        $this->checkTransitionExist($data);
        $this->checkNotStoreBeforeFirstTransition($flow, $data);

        $flowTransition = $flow->transitions()->create([
            'from' => $data['from'],
            'to' => $data['to'],
            'slug' => $data['slug'] ?? null,
        ]);

        event(new FlowTransitionStoreEvent($flowTransition));

        return $flowTransition;
    }

    /**
     * show flow transition
     *
     * @param int $flow_transition_id
     * @param array $with
     *
     * @return FlowTransition
     */
    public function show(int $flow_transition_id, array $with = []): FlowTransition
    {
        return FlowTransition::findOrFail($flow_transition_id)->load($with);
    }

    /**
     * update flow transition
     *
     * @param int $flow_transition_id
     * @param array $data
     *
     * @return FlowTransition
     * @throws FlowInactiveException
     * @throws FlowTransitionInvalidException
     * @throws FlowTransitionStateEndNotInFromException
     * @throws FlowTransitionStateStartNotInToException
     * @throws FlowTransitionStateDriverFromAndToNotEqualException
     * @throws FlowTransitionExistException
     * @throws FlowTransitionSlugExistException
     * @throws FlowTransitionFromStateStartNotMoveException
     */
    public function update(int $flow_transition_id, array $data = []): FlowTransition
    {
        $flowTransition = $this->show($flow_transition_id, ['flow']);

        $this->checkFlowInactive($flowTransition->flow);

        if (array_key_exists('from', $data)) {
            $this->checkStateEndNotInFrom($data);
            $this->checkFromStateStartNotMove($flowTransition, $data);

            $flowTransition->from = $data['from'];
        }

        if (array_key_exists('to', $data)) {
            $this->checkStateStartNotInTo($data);

            $flowTransition->to = $data['to'];
        }

        $this->checkFromAndToIsEqual($data);
        $this->checkStateDriverFromAndToNotEqual($data);
        $this->checkTransitionExist($data, $flow_transition_id);

        if (isset($data['slug'])) {
            $this->checkSlugExist($flowTransition->flow, $data, $flow_transition_id);

            $flowTransition->slug = $data['slug'];
        }

        if (isset($data['role_id'])) {
            $flowTransition->role_id = $data['role_id'];
        }

        $flowTransition->save();

        event(new FlowTransitionUpdateEvent($flowTransition, $data));

        return $flowTransition;
    }

    /**
     * delete flow transition
     *
     * @param int $flow_transition_id
     *
     * @return FlowTransition
     * @throws FlowTransitionHaveAtLeastOneTransitionFromTheStartBeginningException
     */
    public function delete(int $flow_transition_id): FlowTransition
    {
        $flow_transition = $this->show($flow_transition_id);

        $this->checkTransitionHaveAtLeastOneTransitionFromTheStartBeginning($flow_transition);

        // @todo: before delete remove tasks

        $flow_transition->delete();

        event(new FlowTransitionDeleteEvent($flow_transition));

        return $flow_transition;
    }
}
