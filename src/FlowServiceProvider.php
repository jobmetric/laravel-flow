<?php

namespace JobMetric\Flow;

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
}
