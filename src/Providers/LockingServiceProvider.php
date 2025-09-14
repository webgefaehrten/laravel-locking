<?php

namespace Webgefaehrten\Locking\Providers;

use Illuminate\Support\ServiceProvider;
use Webgefaehrten\Locking\Console\InstallCommand;
use Webgefaehrten\Locking\Console\UnlockExpiredLocksCommand;

/**
 * DE: Service Provider des Locking-Pakets. Veröffentlicht Konfiguration, Migrationen und Channels,
 *     registriert Befehle und plant den Aufräum-Job je nach Tenancy-Einstellung.
 * EN: Locking package service provider. Publishes config, migrations and channels,
 *     registers commands and schedules the cleanup job depending on tenancy settings.
 */
class LockingServiceProvider extends ServiceProvider
{
    /**
     * DE: Bootstrap-Phase: Publishing, Migrations laden und Scheduler konfigurieren.
     * EN: Bootstrap phase: publish assets, load migrations and configure the scheduler.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/locking.php' => config_path('locking.php'),
        ], 'locking-config');

        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'locking-migrations');

        $this->publishes([
            __DIR__.'/../../routes/channels.stub.php' => base_path('routes/channels.php'),
        ], 'locking-channels');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->app->booted(function () {
            $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);

            if (config('locking.tenancy')) {
                $schedule->command('tenants:run locking:cleanup --timeout='.config('locking.timeout'))
                    ->everyMinutes(config('locking.interval'))
                    ->onOneServer()
                    ->withoutOverlapping()
                    ->runInBackground()
                    ->appendOutputTo(storage_path('logs/locking.log'))
                    ->onQueue(config('locking.queue'));
            } else {
                $schedule->command('locking:cleanup --timeout='.config('locking.timeout'))
                    ->everyMinutes(config('locking.interval'))
                    ->onOneServer()
                    ->withoutOverlapping()
                    ->runInBackground()
                    ->appendOutputTo(storage_path('logs/locking.log'))
                    ->onQueue(config('locking.queue'));
            }
        });
    }

    /**
     * DE: Konfiguration zusammenführen und Konsolenbefehle registrieren.
     * EN: Merge configuration and register console commands.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/locking.php', 'locking');

        $this->commands([
            InstallCommand::class,
            UnlockExpiredLocksCommand::class,
        ]);
    }
}
