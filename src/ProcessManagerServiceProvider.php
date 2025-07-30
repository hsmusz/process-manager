<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Nova;
use Movecloser\ProcessManager\Console\Commands\ProcessManager;
use Movecloser\ProcessManager\Contracts\ProcessesRepository;
use Movecloser\ProcessManager\Nova\Dashboards\Main;
use Movecloser\ProcessManager\Nova\Resources\Process;
use Movecloser\ProcessManager\Nova\Resources\ProcessStep;

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
        $this->publishes([__DIR__ . '/../config/nova.php' => config_path('nova.php')], 'config');

        // Load views if they exist
        if (is_dir(__DIR__ . '/../resources/views')) {
            $this->loadViewsFrom(__DIR__ . '/../resources/views', 'process-manager');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/migrations/2025_07_21_000001_create_processes_table.php'
                => database_path('migrations/2025_07_21_000001_create_processes_table.php'),
            ], 'migrations');

            $this->commands([
                ProcessManager::class,
            ]);
        }

        Nova::resources([
            Process::class,
            ProcessStep::class,
        ]);
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/process-manager.php', 'process-manager');
        $this->mergeConfigFrom(__DIR__ . '/../config/nova.php', 'nova');

        $this->app->bind(ProcessesRepository::class, Repositories\ProcessesRepository::class);

        $this->app->singleton(\Movecloser\ProcessManager\Contracts\ProcessLogger::class, ProcessLogger::class);
    }
}
