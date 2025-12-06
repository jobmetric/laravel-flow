<?php

namespace JobMetric\Flow;

use Illuminate\Contracts\Container\BindingResolutionException;
use JobMetric\EventSystem\Support\EventRegistry;
use JobMetric\Flow\Services\Flow;
use JobMetric\Flow\Services\FlowState;
use JobMetric\Flow\Services\FlowTask;
use JobMetric\Flow\Services\FlowTransition;
use JobMetric\Flow\Support\FlowTaskRegistry;
use JobMetric\PackageCore\Enums\RegisterClassTypeEnum;
use JobMetric\PackageCore\Exceptions\DependencyPublishableClassNotFoundException;
use JobMetric\PackageCore\Exceptions\MigrationFolderNotFoundException;
use JobMetric\PackageCore\Exceptions\RegisterClassTypeNotFoundException;
use JobMetric\PackageCore\PackageCore;
use JobMetric\PackageCore\PackageCoreServiceProvider;
use JobMetric\Translation\TranslationServiceProvider;

class FlowServiceProvider extends PackageCoreServiceProvider
{
    /**
     * @param PackageCore $package
     *
     * @return void
     * @throws MigrationFolderNotFoundException
     * @throws RegisterClassTypeNotFoundException
     * @throws DependencyPublishableClassNotFoundException
     */
    public function configuration(PackageCore $package): void
    {
        $package->name('laravel-workflow')
            ->hasConfig()
            ->hasMigration()
            ->hasRoute()
            ->hasTranslation()
            ->registerDependencyPublishable(TranslationServiceProvider::class)
            ->registerCommand(Commands\MakeTask::class)
            ->registerClass('flow', Flow::class, RegisterClassTypeEnum::SINGLETON())
            ->registerClass('flow-state', FlowState::class, RegisterClassTypeEnum::SINGLETON())
            ->registerClass('flow-transition', FlowTransition::class, RegisterClassTypeEnum::SINGLETON())
            ->registerClass('flow-task', FlowTask::class, RegisterClassTypeEnum::SINGLETON())
            ->registerClass('FlowTaskRegistry', FlowTaskRegistry::class, RegisterClassTypeEnum::SINGLETON());
    }

    /**
     * after boot package
     *
     * @return void
     * @throws BindingResolutionException
     */
    public function afterBootPackage(): void
    {
        // Register events if EventRegistry is available
        // This ensures EventRegistry is available if EventSystemServiceProvider is loaded
        if ($this->app->bound('EventRegistry')) {
            /** @var EventRegistry $registry */
            $registry = $this->app->make('EventRegistry');

            // Flow Events
            $registry->register(\JobMetric\Flow\Events\Flow\FlowStoreEvent::class);
            $registry->register(\JobMetric\Flow\Events\Flow\FlowUpdateEvent::class);
            $registry->register(\JobMetric\Flow\Events\Flow\FlowDeleteEvent::class);
            $registry->register(\JobMetric\Flow\Events\Flow\FlowForceDeleteEvent::class);
            $registry->register(\JobMetric\Flow\Events\Flow\FlowRestoreEvent::class);

            // FlowState Events
            $registry->register(\JobMetric\Flow\Events\FlowState\FlowStateStoreEvent::class);
            $registry->register(\JobMetric\Flow\Events\FlowState\FlowStateUpdateEvent::class);
            $registry->register(\JobMetric\Flow\Events\FlowState\FlowStateDeleteEvent::class);

            // FlowTask Events
            $registry->register(\JobMetric\Flow\Events\FlowTask\FlowTaskStoreEvent::class);
            $registry->register(\JobMetric\Flow\Events\FlowTask\FlowTaskUpdateEvent::class);
            $registry->register(\JobMetric\Flow\Events\FlowTask\FlowTaskDeleteEvent::class);

            // FlowTransition Events
            $registry->register(\JobMetric\Flow\Events\FlowTransition\FlowTransitionStoreEvent::class);
            $registry->register(\JobMetric\Flow\Events\FlowTransition\FlowTransitionUpdateEvent::class);
            $registry->register(\JobMetric\Flow\Events\FlowTransition\FlowTransitionDeleteEvent::class);
        }
    }
}
