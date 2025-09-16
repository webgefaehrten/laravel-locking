<?php

namespace Webgefaehrten\Locking\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Webgefaehrten\Locking\Events\ModelLocked;
use Webgefaehrten\Locking\Events\ModelUnlocked;
use Webgefaehrten\Locking\Models\Lock;

class BroadcastLockEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string 'locked'|'unlocked' */
    protected string $action;
    protected string $domain;
    protected ?string $tenantId;
    protected ?int $lockId = null;
    protected ?string $modelType = null;
    protected ?int $modelId = null;

    public function __construct(string $action, string $domain, ?string $tenantId = null, ?int $lockId = null, ?string $modelType = null, ?int $modelId = null)
    {
        $this->onQueue((string) Config::get('locking.queue', 'default'));

        $this->action = $action;
        $this->domain = $domain;
        $this->tenantId = $tenantId;
        $this->lockId = $lockId;
        $this->modelType = $modelType;
        $this->modelId = $modelId;
    }

    public function handle(): void
    {
        $teardown = $this->initializeTenancyIfNeeded();

        try {
            if ($this->action === 'locked') {
                if ($this->lockId === null) {
                    return;
                }

                $lock = Lock::query()->with('user')->find($this->lockId);
                if (! $lock) {
                    return;
                }

                $event = new ModelLocked($lock, $this->domain);
                Event::dispatch($event);
                $this->broadcast($event);
                return;
            }

            if ($this->action === 'unlocked') {
                if (! $this->modelType || $this->modelId === null) {
                    return;
                }

                $event = new ModelUnlocked($this->domain, $this->modelType, (int) $this->modelId);
                Event::dispatch($event);
                $this->broadcast($event);
                return;
            }
        } finally {
            if ($teardown) {
                $teardown();
            }
        }
    }

    /**
     * Initialisiert optional den Tenant-Kontext. Gibt einen Teardown-Callback zurück, wenn initialisiert.
     */
    protected function initializeTenancyIfNeeded(): ?callable
    {
        if (! (bool) Config::get('locking.tenancy', false)) {
            return null;
        }

        if (! $this->tenantId) {
            return null;
        }

        // Stancl Tenancy v4 (Tenant-Klasse unter Database\Models)
        if (class_exists(\Stancl\Tenancy\Tenancy::class) && class_exists(\Stancl\Tenancy\Database\Models\Tenant::class)) {
            /** @var \Stancl\Tenancy\Tenancy $tenancy */
            $tenancy = \app(\Stancl\Tenancy\Tenancy::class);
            $tenantModelClass = \Stancl\Tenancy\Database\Models\Tenant::class;
            /** @var object|null $tenant */
            $tenant = $tenantModelClass::find($this->tenantId);

            if (! $tenant) {
                return null;
            }

            $tenancy->initialize($tenant);

            return function () use ($tenancy) {
                $tenancy->end();
            };
        }

        // Stancl Tenancy v3 (Tenant-Klasse direkt unter \Stancl\Tenancy)
        if (class_exists(\Stancl\Tenancy\Tenancy::class) && class_exists(\Stancl\Tenancy\Tenant::class)) {
            /** @var \Stancl\Tenancy\Tenancy $tenancy */
            $tenancy = \app(\Stancl\Tenancy\Tenancy::class);
            /** @var \Stancl\Tenancy\Tenant|null $tenant */
            $tenant = \Stancl\Tenancy\Tenant::find($this->tenantId);

            if (! $tenant) {
                return null;
            }

            $tenancy->initialize($tenant);

            return function () use ($tenancy) {
                $tenancy->end();
            };
        }

        return null;
    }

    protected function broadcast(object $event): void
    {
        $shouldSelf = (bool) Config::get('locking.broadcast_self', false);
        if ($shouldSelf) {
            \broadcast($event);
        } else {
            \broadcast($event)->toOthers();
        }
    }
}


