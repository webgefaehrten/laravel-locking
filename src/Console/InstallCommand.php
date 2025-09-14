<?php

namespace Webgefaehrten\Locking\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * DE: Installationsbefehl des Locking-Systems. Veröffentlicht Konfiguration, Channels und Migrationen
 *     (wahlweise tenant-aware) und führt bei zentralem Setup die Migration direkt aus.
 * EN: Installation command for the locking system. Publishes config, channels and migrations
 *     (optionally tenant-aware) and runs migrations immediately in a central setup.
 */
class InstallCommand extends Command
{
    protected $signature = 'locking:install {--tenancy}';
    protected $description = 'Install locking system (publish config + migrations + channels + run migrate)';

    public function handle()
    {
        $isTenancy = $this->option('tenancy');

        // DE: Konfiguration veröffentlichen | EN: Publish configuration
        $this->call('vendor:publish', ['--tag' => 'locking-config']);

        // DE: Broadcast-Channels veröffentlichen | EN: Publish broadcast channels
        $this->call('vendor:publish', ['--tag' => 'locking-channels']);

        // DE: Migrationen veröffentlichen (mit optionalem Tenant-Support) | EN: Publish migrations (optionally tenant-aware)
        if ($isTenancy) {
            $tenantPath = database_path('migrations/tenant');

            if (! File::exists($tenantPath)) {
                File::makeDirectory($tenantPath, 0755, true);
            }

            File::copyDirectory(__DIR__.'/../../database/migrations', $tenantPath);

            $this->info('Locking migrations published to database/migrations/tenant');
            $this->info('Please run: php artisan tenants:migrate');
        } else {
            $this->call('vendor:publish', ['--tag' => 'locking-migrations']);
            $this->call('migrate');
            $this->info('Locking migrations migrated centrally');
        }

        $this->info('Locking system installed successfully!');
    }
}
