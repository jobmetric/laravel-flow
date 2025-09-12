<?php

namespace JobMetric\Flow;

use JobMetric\Flow\Services\FlowManager;
use JobMetric\Flow\Services\FlowStateManager;
use JobMetric\Flow\Services\FlowTaskManager;
use JobMetric\Flow\Services\FlowTransitionManager;
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
        $package
            ->name('laravel-workflow')
            ->hasConfig()
            ->hasMigration()
            ->hasRoute()
            ->hasTranslation()
            ->registerDependencyPublishable(TranslationServiceProvider::class)
            ->registerCommand(Commands\MakeFlow::class)
            ->registerCommand(Commands\MakeTask::class)
            ->registerClass('Flow', FlowManager::class)
            ->registerClass('FlowState', FlowStateManager::class)
            ->registerClass('FlowTransition', FlowTransitionManager::class)
            ->registerClass('FlowTask', FlowTaskManager::class);
    }
}
