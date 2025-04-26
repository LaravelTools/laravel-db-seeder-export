<?php

namespace LaravelToolkit\DbSeederExport;

use Illuminate\Support\ServiceProvider;
use LaravelToolkit\DbSeederExport\Commands\ExportSeederData;

class DatabaseSeederExportServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Register the command
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExportSeederData::class,
            ]);
            
            // Publish config
            $this->publishes([
                __DIR__ . '/config/db-seeder-export.php' => config_path('db-seeder-export.php'),
            ], 'db-seeder-export-config');
            
            // Publish email templates
            $this->publishes([
                __DIR__ . '/resources/views' => resource_path('views/vendor/db-seeder-export'),
            ], 'db-seeder-export-views');
        }
        
        // Load views
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'db-seeder-export');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/config/db-seeder-export.php', 'db-seeder-export'
        );
    }
}