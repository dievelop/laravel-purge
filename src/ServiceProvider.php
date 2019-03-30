<?php

namespace Dievelop\LaravelPurge;

use Dievelop\LaravelPurge\Commands\LaravelPurgeFilesCommand;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/laravel-purge.php' => config_path('laravel-purge.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                LaravelPurgeFilesCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laravel-purge-defaults.php', 'laravel-purge'
        );
    }
}
