<?php

namespace Webgefaehrten\Locking\Traits;

use Webgefaehrten\Locking\Models\Lock;
use Webgefaehrten\Locking\Events\ModelLocked;
use Webgefaehrten\Locking\Events\ModelUnlocked;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Carbon\Carbon;

/**
 * DE: Trait für pessimistische Sperren (exklusive Bearbeitung pro Nutzer und Model-Instanz).
 *
 * Funktionsweise:
 *  - `lock()`: setzt/erneuert die Sperre für den aktuellen Benutzer, broadcastet `ModelLocked`.
 *    Gibt `false` zurück, wenn bereits ein anderer Benutzer gesperrt hat (optional `handleLockMessage` im Model).
 *  - `unlock()`: entfernt die eigene Sperre und broadcastet `ModelUnlocked`.
 *  - `isLocked()`: prüft, ob die Sperre existiert und noch gültig ist (Timeout wird bereinigt).
 *  - `lockedBy()`: liefert den sperrenden Benutzer (oder `null`).
 *  - `lockRecord()`: morphOne-Relation zum `Lock`-Model.
 *
 * Voraussetzungen:
 *  - Tabelle `locks` vorhanden (Migration)
 *  - Authentifizierung vorhanden (`Auth::id()`)
 *  - Broadcasting konfiguriert (PrivateChannel `tenant.{domain}.locks`)
 *
 * Optional:
 *  - Definiere `handleLockMessage(string $message): void` im Model für UI-Feedback (z. B. Toast).
 */
trait PessimisticLockingTrait
{
    /**
     * Setzt oder erneuert die Sperre für den aktuellen Benutzer.
     */
    public function lock(): bool
    {
        $timeout = (int) Config::get('locking.timeout');
        $domain  = $this->resolveDomain();
        $existingLock = $this->lockRecord;

        // Alte Sperre abräumen (Timeout oder älter als 1 Stunde)
        if ($existingLock && (
            $existingLock->locked_at->diffInMinutes(Carbon::now()) >= $timeout
            || $existingLock->locked_at->diffInHours(Carbon::now()) >= 1
        )) {
            $existingLock->delete();
        }

        // Wenn bereits fremd gesperrt → abbrechen
        if ($this->lockRecord && $this->lockRecord->locked_by !== Auth::id()) {
            $this->fireLockMessage("Eintrag #{$this->id} ist bereits von einem anderen Benutzer gesperrt.");
            return false;
        }

        // Sperre setzen oder erneuern
        $lock = $this->lockRecord()->updateOrCreate(
            ['lockable_type' => static::class, 'lockable_id' => $this->id],
            ['locked_by' => Auth::id(), 'locked_at' => Carbon::now()]
        );

        // Event lokal + broadcasten
        $event = new ModelLocked($lock, $domain);
        Event::dispatch($event);

        $this->broadcastEvent($event);

        return true;
    }

    /**
     * Entfernt die eigene Sperre und broadcastet `ModelUnlocked`.
     */
    public function unlock(): void
    {
        $domain = $this->resolveDomain();
        $lock   = $this->lockRecord;

        if ($lock && $lock->locked_by === Auth::id()) {
            $lock->delete();

            $event = new ModelUnlocked($domain, static::class, $this->id);
            Event::dispatch($event);

            $this->broadcastEvent($event);
        }
    }

    /**
     * Prüft, ob das Model aktuell (durch einen anderen Benutzer) gesperrt ist.
     * Bereinigt abgelaufene Sperren automatisch.
     */
    public function isLocked(): bool
    {
        $timeout = (int) Config::get('locking.timeout');
        $lock    = $this->lockRecord;

        if (!$lock) {
            return false;
        }

        if (
            $lock->locked_at->diffInMinutes(Carbon::now()) >= $timeout
            || $lock->locked_at->diffInHours(Carbon::now()) >= 1
        ) {
            $lock->delete();
            return false;
        }

        return $lock->locked_by !== Auth::id();
    }

    /**
     * Liefert den sperrenden Benutzer oder null, falls keine Sperre existiert.
     */
    public function lockedBy()
    {
        return $this->lockRecord ? $this->lockRecord->user : null;
    }

    /**
     * MorphOne-Relation zum Lock-Datensatz.
     */
    public function lockRecord()
    {
        return $this->morphOne(Lock::class, 'lockable');
    }

    /**
     * Helper für optionale UI-Messages im Model.
     */
    protected function fireLockMessage(string $message): void
    {
        if (method_exists($this, 'handleLockMessage')) {
            $this->handleLockMessage($message);
        }
    }

    /**
     * Ermittelt die Broadcast-Domain.
     */
    protected function resolveDomain(): string
    {
        if ((bool) Config::get('locking.tenancy', false)) {
            if (function_exists('tenant')) {
                $tenant = \tenant();
                if ($tenant && isset($tenant->primary_domain) && isset($tenant->primary_domain->domain)) {
                    return (string) $tenant->primary_domain->domain;
                }
            }
        }

        return 'default';
    }

    /**
     * Broadcast-Helper mit Config-Schalter (broadcast_self).
     */
    protected function broadcastEvent(object $event): void
    {
        $shouldSelf = (bool) Config::get('locking.broadcast_self', false);

        if ($shouldSelf) {
            broadcast($event);
        } else {
            broadcast($event)->toOthers();
        }
    }
}