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

        // DE: Migrationen veröffentlichen (mit optionalem Tenant-Support), aber überspringen, wenn bereits vorhanden
        // EN: Publish migrations (optionally tenant-aware), but skip if already present
        $srcDir = __DIR__ . '/../../database/migrations';
        $destDir = $isTenancy ? database_path('migrations/tenant') : database_path('migrations');

        if (! File::exists($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $existing = glob($destDir . '/*_create_locks_table.php') ?: [];
        if (! empty($existing)) {
            $this->info('Locking migration already exists. Skipping publish.');
        } else {
            $srcFiles = glob($srcDir . '/*_create_locks_table.php') ?: [];

            if (empty($srcFiles)) {
                // Fallback: kopiere alle Dateien aus dem Ordner, falls keine Namensübereinstimmung gefunden wird
                foreach ((array) glob($srcDir . '/*.php') as $file) {
                    $target = $destDir . '/' . basename($file);
                    if (! File::exists($target)) {
                        File::copy($file, $target);
                    }
                }
            } else {
                foreach ($srcFiles as $file) {
                    $target = $destDir . '/' . basename($file);
                    if (! File::exists($target)) {
                        File::copy($file, $target);
                    }
                }
            }

            $this->info($isTenancy
                ? 'Locking migration published to database/migrations/tenant'
                : 'Locking migration published to database/migrations'
            );
        }

        if ($isTenancy) {
            $this->info('Please run: php artisan tenants:migrate');
        } else {
            $this->info('Migrations published. Please run: php artisan migrate');
        }

        $this->info('Locking system installed successfully!');
    }
}
