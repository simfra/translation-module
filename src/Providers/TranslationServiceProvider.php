<?php

namespace Simfra\TranslationsModule\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Simfra\TranslationsModule\DatabaseTranslationLoader;
use Illuminate\Translation\Translator;

class TranslationServiceProvider extends ServiceProvider
{
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Simfra\TranslationsModule\Console\Commands\TranslationsInstallCommand::class,
                \Simfra\TranslationsModule\Console\Commands\TranslationsUninstallCommand::class,
                \Simfra\TranslationsModule\Console\Commands\PublishTranslationsCommand::class,
            ]);
        }
        $this->mergeConfigFrom(__DIR__ . '/../../config/translations.php', 'translations');
    }

    public function boot()
    {
        if (config('translations.use_database_loader', true)) {
            $loader = new DatabaseTranslationLoader();
            $locale = $this->app['config']['app.locale'] ?? 'pl';
            $translator = new Translator($loader, $locale);
            $translator->setFallback($this->app['config']['app.fallback_locale'] ?? 'en');

            $this->app->instance('translation.loader', $loader);
            $this->app->instance('translator', $translator);

            Log::info('TranslationServiceProvider: Custom translator and loader registered in boot');
        }

        $this->registerRoutes();

        $this->publishes([
            __DIR__ . '/../../config/translations.php' => config_path('translations.php'),
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
            __DIR__ . '/../../resources/js/pages' => resource_path('js/pages'),
            __DIR__ . '/../../resources/js/Components' => resource_path('js/Components'),
            __DIR__ . '/../../resources/lang' => resource_path('lang/vendor/translations-module'),
        ], ['translations-all', 'translations-module']);

        $this->autoPublishResources();
        $this->registerInertiaComponents();
    }

    protected function registerRoutes()
    {
        Route::middleware(['web'])
            ->prefix(config('translations.route_prefix', 'translations'))
            ->group(__DIR__ . '/../../routes/web.php');
    }

    protected function autoPublishResources()
    {
        $resources = [
            [
                'type' => 'directory',
                'source' => __DIR__ . '/../../resources/js/pages',
                'destination' => resource_path('js/pages'),
            ],
            [
                'type' => 'directory',
                'source' => __DIR__ . '/../../resources/js/Components',
                'destination' => resource_path('js/Components'),
            ],
        ];
        foreach ($resources as $r) {
            if ($r['type'] === 'file' && File::exists($r['source']) && !File::exists($r['destination'])) {
                File::copy($r['source'], $r['destination']);
            }
            if ($r['type'] === 'directory' && !File::isDirectory($r['destination'])) {
                File::copyDirectory($r['source'], $r['destination']);
            }
        }
    }

    protected function registerInertiaComponents()
    {
        Inertia::share('moduleComponents', fn () => [
            'translations-module' => [
                'namespace' => 'Translations',
                'path' => resource_path('js/pages'),
            ],
        ]);
    }
}
