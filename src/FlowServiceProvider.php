<?php

namespace JobMetric\Flow;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use JobMetric\Flow\Models\Flow;
use JobMetric\Translation\TranslationServiceProvider;

class FlowServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected string $namespace = 'JobMetric\Flow\Http\Controllers';

    public function register(): void
    {
        $this->app->bind('Flow', function ($app) {
            return new FlowManager($app);
        });

        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'workflow');
    }

    /**
     * boot provider
     *
     * @return void
     */
    public function boot(): void
    {
        if($this->app->runningInConsole()) {
            // load migration
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            // register publishable
            $this->registerPublishables();

            $this->commands([
                Commands\MakeFlow::class,
                Commands\MakeTask::class,
            ]);
        }

        // set translations
        $this->loadTranslationsFrom(realpath(__DIR__.'/../lang'), 'flow');

        // set route


        Route::prefix('workflow')->name('workflow.')->namespace($this->namespace)->group(realpath(__DIR__.'/../routes/route.php'));
    }

    /**
     * register publishables
     *
     * @return void
     */
    protected function registerPublishables(): void
    {
        // run dependency publishable
        $this->publishes(self::pathsToPublish(TranslationServiceProvider::class), 'translation');

        // publish config
        $this->publishes([
            realpath(__DIR__.'/../config/config.php') => config_path('workflow.php')
        ], ['workflow', 'flow-config']);

        // publish migration
        $this->publishes([
            realpath(__DIR__.'/../database/migrations') => database_path('migrations')
        ], ['workflow', 'flow-migrations']);
    }
}
