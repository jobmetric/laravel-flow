<?php

namespace JobMetric\Flow;

use Illuminate\Support\ServiceProvider;
use JobMetric\Translation\TranslationServiceProvider;

class FlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('Flow', function ($app) {
            return new Flow($app);
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
        }

        // set translations
        $this->loadTranslationsFrom(realpath(__DIR__.'/../lang'), 'flow');
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
        ], 'flow-config');

        // publish migration
        $this->publishes([
            realpath(__DIR__.'/../database/migrations') => database_path('migrations')
        ], 'flow-migrations');
    }
}
