<?php 

namespace Webgefaehrten\Locking\Console;

use Illuminate\Console\Command;
use Webgefaehrten\Locking\Models\Lock;
use Webgefaehrten\Locking\Events\ModelUnlocked;

/**
 * DE: Konsolenbefehl, der abgelaufene Sperren (Locks) aufräumt und entsprechende Events broadcastet.
 * EN: Console command that cleans up expired locks and broadcasts the corresponding events.
 */
class UnlockExpiredLocksCommand extends Command
{
    protected $signature = 'locking:cleanup {--timeout=} {--tenants=*}';
    protected $description = 'Entfernt abgelaufene Locks nach Timeout und broadcastet Unlock-Events';

    public function handle()
    {
        $timeout = $this->option('timeout') ?? config('locking.timeout');

        if (config('locking.tenancy')) {
            return $this->runTenantAware($timeout);
        }

        return $this->runCentral($timeout);
    }

    protected function runTenantAware(int $timeout)
    {
        $expired = Lock::where('locked_at', '<', now()->subMinutes($timeout))->get();
        $count = 0;

        foreach ($expired as $lock) {
            $domain = tenant()->primary_domain->domain ?? 'default';
            broadcast(new ModelUnlocked(
                $domain,
                $lock->lockable_type,
                $lock->lockable_id
            ))->toOthers();
            $lock->delete();
            $count++;
        }

        $this->info("[$count] Locks entfernt (Tenant ".tenant('id').")");
    }

    protected function runCentral(int $timeout)
    {
        $expired = Lock::where('locked_at', '<', now()->subMinutes($timeout))->get();
        $count = 0;

        foreach ($expired as $lock) {
            broadcast(new ModelUnlocked(
                'default',
                $lock->lockable_type,
                $lock->lockable_id
            ))->toOthers();
            $lock->delete();
            $count++;
        }

        $this->info("[$count] zentrale Locks entfernt");
    }
}
