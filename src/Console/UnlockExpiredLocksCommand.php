<?php

namespace Webgefaehrten\Locking\Console;

use Illuminate\Console\Command;
use Webgefaehrten\Locking\Models\Lock;
use Webgefaehrten\Locking\Events\ModelUnlocked;
use Stancl\Tenancy\Tenant;

class UnlockExpiredLocksCommand extends Command
{
    protected $signature = 'locking:cleanup {--timeout=} {--tenants=*}';
    protected $description = 'Entfernt abgelaufene Locks nach Timeout und broadcastet Unlock-Events';

    public function handle()
    {
        $timeout = (int) ($this->option('timeout') ?? config('locking.timeout', 5));

        if (config('locking.tenancy')) {
            $tenantIds = $this->option('tenants');

            $tenants = $tenantIds
                ? Tenant::whereIn('id', $tenantIds)->get()
                : Tenant::all();

            tenancy()->runForMultiple(
                $tenants,
                function (Tenant $tenant) use ($timeout) {
                    $this->cleanupLocks($timeout, $tenant->primary_domain->domain ?? 'default');
                    $this->info("Locks für Tenant {$tenant->id} bereinigt");
                }
            );
        } else {
            $this->cleanupLocks($timeout, 'default');
            $this->info("Zentrale Locks bereinigt");
        }
    }

    protected function cleanupLocks(int $timeout, string $domain): void
    {
        $expired = Lock::where('locked_at', '<', now()->subMinutes($timeout))->get();
        $count = 0;

        foreach ($expired as $lock) {
            broadcast(new ModelUnlocked(
                $domain,
                $lock->lockable_type,
                $lock->lockable_id
            ))->toOthers();

            $lock->delete();
            $count++;
        }

        $this->line(" → [$count] Locks entfernt (Domain: {$domain})");
    }
}
