<?php

namespace JobMetric\Flow\Services;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use JobMetric\Flow\Events\FlowTransition\FlowTransitionStoreEvent;
use JobMetric\Flow\Exceptions\FlowInactiveException;
use JobMetric\Flow\Exceptions\FlowTransitionExistException;
use JobMetric\Flow\Exceptions\FlowTransitionFromNotSetException;
use JobMetric\Flow\Exceptions\FlowTransitionInvalidException;
use JobMetric\Flow\Exceptions\FlowTransitionSlugExistException;
use JobMetric\Flow\Exceptions\FlowTransitionStateDriverFromAndToNotEqualException;
use JobMetric\Flow\Exceptions\FlowTransitionStateEndNotInFromException;
use JobMetric\Flow\Exceptions\FlowTransitionStateStartNotInToException;
use JobMetric\Flow\Exceptions\FlowTransitionToNotSetException;
use JobMetric\Flow\Facades\Flow;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Flow\Models\FlowTransition;
use JobMetric\Metadata\JMetadata;

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
     * @return FlowState
     */
    public function show(int $flow_transition_id, array $with = []): FlowState
    {
        return FlowTransition::findOrFail($flow_transition_id)->load($with);
    }
}
