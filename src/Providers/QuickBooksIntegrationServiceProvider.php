<?php

namespace E3DevelopmentSolutions\LaravelQuickBooksIntegration\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Middleware\QuickBooksOAuthMiddleware;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Commands\ScaffoldQuickBooksModel;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Services\QuickBooksService;

class QuickBooksIntegrationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/quickbooks.php',
            'quickbooks'
        );

        // Register the QuickBooks service
        $this->app->singleton(QuickBooksService::class, function ($app) {
            return new QuickBooksService($app['config']['quickbooks']);
        });

        // Register the service alias
        $this->app->alias(QuickBooksService::class, 'quickbooks');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/quickbooks.php' => config_path('quickbooks.php'),
        ], 'quickbooks-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../Database/Migrations' => database_path('migrations'),
        ], 'quickbooks-migrations');

        // Publish views
        $this->publishes([
            __DIR__ . '/../Views' => resource_path('views/vendor/quickbooks'),
        ], 'quickbooks-views');

        // Publish assets (if any)
        $this->publishes([
            __DIR__ . '/../../resources/assets' => public_path('vendor/quickbooks'),
        ], 'quickbooks-assets');

        // Register middleware
        $router = $this->app['router'];
        $router->aliasMiddleware('quickbooks.oauth', QuickBooksOAuthMiddleware::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ScaffoldQuickBooksModel::class,
            ]);
        }

        // Load package routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        // Load package views
        $this->loadViewsFrom(__DIR__ . '/../Views', 'quickbooks');

        // Load package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // Register package translations
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'quickbooks');

        // Publish translations
        $this->publishes([
            __DIR__ . '/../../resources/lang' => resource_path('lang/vendor/quickbooks'),
        ], 'quickbooks-translations');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            QuickBooksService::class,
            'quickbooks',
        ];
    }
}

