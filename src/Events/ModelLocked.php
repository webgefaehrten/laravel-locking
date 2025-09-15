<?php

namespace Webgefaehrten\Locking\Events;

use Webgefaehrten\Locking\Models\Lock;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * DE: Broadcast-Event, das signalisiert, dass ein Model gesperrt wurde.
 * EN: Broadcast event indicating that a model has been locked.
 */
class ModelLocked implements ShouldBroadcast
{
    /**
     * DE: Der Lock-Datensatz inkl. geladener Benutzer-Relation.
     * EN: The lock record including the eager-loaded user relation.
     */
    public $lock;

    /**
     * DE: Domain bzw. Mandant, über den der Channel benannt wird.
     * EN: Domain/tenant used to name the broadcast channel.
     */
    protected $domain;

    public function __construct(Lock $lock, string $domain)
    {
        $this->lock = $lock->load('user');
        $this->domain = $domain;
    }

    /**
     * DE: Zielkanal für das Event.
     * EN: Target channel for the event broadcast.
     */
    public function broadcastOn()
    {
        if (config('locking.tenancy')) {
            return [new PrivateChannel("tenant.{$this->domain}.locks")];
        }

        return [new PrivateChannel("locks.{$this->domain}")];
    }

    /**
     * DE: Ereignisname.
     * EN: Event name.
     */
    public function broadcastAs()
    {
        return 'ModelLocked';
    }

    /**
     * DE: Payload des Events für Clients (Frontends, Listener).
     * EN: Event payload for clients (frontends, listeners).
     */
    public function broadcastWith()
    {
        return [
            'type' => 'locked',
            'model_type' => $this->lock->lockable_type,
            'model_id' => $this->lock->lockable_id,
            'user' => $this->lock->user->only(['id', 'name']),
            'locked_at' => $this->lock->locked_at,
            'message' => "Eintrag #{$this->lock->lockable_id} wird gerade von {$this->lock->user->name} bearbeitet.",
        ];
    }
}
