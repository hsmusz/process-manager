<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Nova;
use Movecloser\ProcessManager\Console\Commands\ProcessManager;
use Movecloser\ProcessManager\Contracts\ProcessesRepository;
use Movecloser\ProcessManager\Nova\Resources\Process;

class ProcessManagerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        AboutCommand::add('Process Manager', fn() => ['Version' => '1.0.0']);

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publish configuration
        $this->publishes([__DIR__ . '/../config/process-manager.php' => config_path('process-manager.php')], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessManager::class,
            ]);
        }

        $this->app->bind(ProcessesRepository::class, Repositories\ProcessesRepository::class);

        Nova::resources([
            Process::class
        ]);
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/process-manager.php', 'process-manager'
        );

        $this->app->singleton(\Movecloser\ProcessManager\Contracts\ProcessLogger::class, ProcessLogger::class);
    }
}
