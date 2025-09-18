<?php

namespace Webgefaehrten\Locking\Providers;

use Illuminate\Support\ServiceProvider;
use Webgefaehrten\Locking\Console\InstallCommand;
use Webgefaehrten\Locking\Console\UnlockExpiredLocksCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Webgefaehrten\Locking\Http\Middleware\CheckLock as CheckLockMiddleware;
use Webgefaehrten\Locking\Http\Middleware\CheckOptimisticLock as CheckOptimisticLockMiddleware;

class LockingServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Paket-Middleware registrieren
        $this->app->resolving(Router::class, function (Router $router) {
            $router->aliasMiddleware('locking.check', CheckLockMiddleware::class);
            $router->aliasMiddleware('locking.optimistic', CheckOptimisticLockMiddleware::class);
        });

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
                ->appendOutputTo(storage_path('logs/locking.log'));

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
