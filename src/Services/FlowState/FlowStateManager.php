<?php

namespace JobMetric\Flow\Services\FlowState;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use JobMetric\Flow\Enums\TableFlowStateFieldTypeEnum;
use JobMetric\Flow\Events\FlowStateStoreEvent;
use JobMetric\Flow\Exceptions\FlowInactiveException;
use JobMetric\Flow\Exceptions\FlowStateStartTypeIsExistException;
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
}
