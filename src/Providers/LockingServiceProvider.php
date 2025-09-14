<?php

namespace Webgefaehrten\Locking\Providers;

use Illuminate\Support\ServiceProvider;
use Webgefaehrten\Locking\Console\InstallCommand;
use Webgefaehrten\Locking\Console\UnlockExpiredLocksCommand;
use Illuminate\Console\Scheduling\Schedule;

/**
 * DE: Service Provider des Locking-Pakets. Veröffentlicht Konfiguration, Migrationen und Channels,
 *     registriert Befehle und plant den Aufräum-Job je nach Tenancy-Einstellung.
 * EN: Locking package service provider. Publishes config, migrations and channels,
 *     registers commands and schedules the cleanup job depending on tenancy settings.
 */
class LockingServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/locking.php' => config_path('locking.php'),
        ], 'locking-config');

        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'locking-migrations');

        $this->publishes([
            __DIR__ . '/../../routes/channels.stub.php' => base_path('routes/channels.php'),
        ], 'locking-channels');

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            $interval = (int) config('locking.interval', 5);
            $command = config('locking.tenancy')
                ? "tenants:run locking:cleanup --timeout=" . config('locking.timeout')
                : "locking:cleanup --timeout=" . config('locking.timeout');

            $event = $schedule->command($command)
                ->onOneServer()
                ->withoutOverlapping()
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/locking.log'))
                ->onQueue(config('locking.queue', 'locking'));

            // Laravel 12-kompatibles Intervall-Mapping
            match ($interval) {
                1  => $event->everyMinute(),
                5  => $event->everyFiveMinutes(),
                10 => $event->everyTenMinutes(),
                15 => $event->everyFifteenMinutes(),
                30 => $event->everyThirtyMinutes(),
                default => $event->hourly(),
            };
        });
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/locking.php', 'locking');

        $this->commands([
            InstallCommand::class,
            UnlockExpiredLocksCommand::class,
        ]);
    }
}
