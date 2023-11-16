<?php

namespace JobMetric\Flow\Services\Flow;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use JobMetric\Flow\Models\Flow;
use JobMetric\Metadata\JMetadata;
use Throwable;

class FlowManager
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
     * store flow
     *
     * @param array $data
     *
     * @return Flow
     */
    public function store(array $data = []): Flow
    {
        return Flow::create($data);
    }
}
