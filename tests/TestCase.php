<?php

namespace JobMetric\Flow\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JobMetric\CustomField\CustomFieldServiceProvider;
use JobMetric\EventSystem\EventSystemServiceProvider;
use JobMetric\Flow\FlowServiceProvider;
use JobMetric\Flyron\FlyronServiceProvider;
use JobMetric\Form\FormServiceProvider;
use JobMetric\Language\LanguageServiceProvider;
use JobMetric\Language\Models\Language;
use JobMetric\Translation\TranslationServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            CustomFieldServiceProvider::class,
            EventSystemServiceProvider::class,
            FlyronServiceProvider::class,
            FormServiceProvider::class,
            LanguageServiceProvider::class,
            TranslationServiceProvider::class,
            FlowServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        loadMigrationPath(__DIR__ . '/database/migrations');

        if (!Language::query()->exists()) {
            Language::factory()->english()->create();
        }
    }
}
