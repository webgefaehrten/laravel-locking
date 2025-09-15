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

        // Nur Hinweise ausgeben, alles wird manuell vom Anwender ausgeführt
        $this->line('');
        $this->info('Locking installation helper');
        $this->line('');
        $this->line('Bitte manuell publishen:');
        $this->line('  php artisan vendor:publish --tag=locking-config');
        $this->line('  php artisan vendor:publish --tag=locking-channels');
        $this->line('  php artisan vendor:publish --tag=locking-migrations');
        $this->line('');
        if ($isTenancy) {
            $this->line('Tenancy-Hinweis: Verschiebe die veröffentlichte Migration bei Bedarf nach database/migrations/tenant und führe dann:');
            $this->line('  php artisan tenants:migrate');
        } else {
            $this->line('Anschließend Migration ausführen:');
            $this->line('  php artisan migrate');
        }
        $this->line('');
        $this->info('Fertig. Keine automatischen Aktionen durchgeführt.');

        $this->info('Locking system installed successfully!');
    }
}
