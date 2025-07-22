<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Movecloser\ProcessManager\Console\Commands\ProcessManager;
use Movecloser\ProcessManager\Interfaces\ProcessesRepository;

class ProcessManagerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        AboutCommand::add('Process Manager', fn() => ['Version' => '1.0.0']);

        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/process-manager.php' => config_path('process-manager.php'),
        ], 'config');

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessManager::class,
            ]);
        }

        $this->app->bind(ProcessesRepository::class, new Repositories\ProcessesRepository());
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

        $this->app->singleton(\Movecloser\ProcessManager\Interfaces\ProcessLogger::class, ProcessLogger::class);
    }
}
