<?php

namespace Webgefaehrten\Locking\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * DE: Broadcast-Event, das signalisiert, dass ein Model entsperrt wurde.
 * EN: Broadcast event indicating that a model has been unlocked.
 */
class ModelUnlocked implements ShouldBroadcast
{
    /**
     * DE: Domain bzw. Mandant, über den der Channel benannt wird.
     * EN: Domain/tenant used to name the broadcast channel.
     */
    protected $domain;

    /**
     * DE: Vollqualifizierter Klassenname des Modells.
     * EN: Fully-qualified class name of the model.
     */
    protected $modelType;

    /**
     * DE: Primärschlüssel des Modells.
     * EN: Primary key of the model.
     */
    protected $modelId;

    public function __construct(string $domain, string $modelType, int $modelId)
    {
        $this->domain = $domain;
        $this->modelType = $modelType;
        $this->modelId = $modelId;
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
        return 'ModelUnlocked';
    }

    /**
     * DE: Payload des Events für Clients (Frontends, Listener).
     * EN: Event payload for clients (frontends, listeners).
     */
    public function broadcastWith()
    {
        return [
            'type' => 'unlocked',
            'model_type' => $this->modelType,
            'model_id' => $this->modelId,
            'message' => "Eintrag #{$this->modelId} wurde wieder freigegeben.",
        ];
    }
}
