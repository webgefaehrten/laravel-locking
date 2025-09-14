<?php

namespace Webgefaehrten\Locking\Traits;

use Webgefaehrten\Locking\Models\Lock;
use Webgefaehrten\Locking\Events\ModelLocked;
use Webgefaehrten\Locking\Events\ModelUnlocked;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Carbon;

/**
 * DE: Trait für pessimistische Sperren (exklusive Bearbeitung pro Nutzer und Model-Instanz).
 *
 * Funktionsweise:
 *  - `lock($domain)`: setzt/erneuert die Sperre für den aktuellen Benutzer, broadcastet `ModelLocked`.
 *    Gibt `false` zurück, wenn bereits ein anderer Benutzer gesperrt hat (und ruft optional `handleLockMessage`).
 *  - `unlock($domain)`: entfernt die eigene Sperre und broadcastet `ModelUnlocked`.
 *  - `isLocked()`: prüft, ob die Sperre existiert und noch gültig ist (Timeout wird bereinigt).
 *  - `lockedBy()`: liefert den sperrenden Benutzer (oder `null`).
 *  - `lockRecord()`: morphOne-Relation zum `Lock`-Model.
 *
 * Voraussetzungen:
 *  - Tabelle `locks` vorhanden (Migration ausführen)
 *  - Authentifizierung vorhanden (nutzt `Auth::id()`)
 *  - Broadcasting konfiguriert (PrivateChannel `locks.{domain}`)
 *
 * Optional:
 *  - Definiere `handleLockMessage(string $message): void` im Model, um UI-Feedback (Flash/Toast) auszugeben.
 */
trait PessimisticLockingTrait
{
    /**
     * Setzt oder erneuert die Sperre für den aktuellen Benutzer.
     *
     * @param string $domain   Domain/Mandant für den Broadcast-Kanal (z. B. Tenant-Domain)
     * @return bool            true, wenn gesperrt wurde; false, wenn bereits fremd gesperrt
     */
    public function lock(string $domain): bool
    {
        $timeout = (int) Config::get('locking.timeout');
        $existingLock = $this->lockRecord;

        if ($existingLock && (
            $existingLock->locked_at->diffInMinutes(Carbon::now()) >= $timeout
            || $existingLock->locked_at->diffInHours(Carbon::now()) >= 1
        )) {
            $existingLock->delete();
        }

        if ($this->lockRecord && $this->lockRecord->locked_by !== \Illuminate\Support\Facades\Auth::id()) {
            $this->fireLockMessage("Eintrag #{$this->id} ist bereits von einem anderen Benutzer gesperrt.");
            return false;
        }

        $lock = $this->lockRecord()->updateOrCreate(
            ['lockable_type' => static::class, 'lockable_id' => $this->id],
            ['locked_by' => \Illuminate\Support\Facades\Auth::id(), 'locked_at' => Carbon::now()]
        );

        Event::dispatch(new ModelLocked($lock, $domain));
        return true;
    }

    /**
     * Entfernt die eigene Sperre und broadcastet `ModelUnlocked`.
     *
     * @param string $domain
     * @return void
     */
    public function unlock(string $domain): void
    {
        $lock = $this->lockRecord;
        if ($lock && $lock->locked_by === \Illuminate\Support\Facades\Auth::id()) {
            $lock->delete();
            Event::dispatch(new ModelUnlocked($domain, static::class, $this->id));
        }
    }

    /**
     * Prüft, ob das Model aktuell (durch einen anderen Benutzer) gesperrt ist.
     * Bereinigt abgelaufene Sperren automatisch.
     *
     * @return bool  true, wenn durch anderen Benutzer gesperrt; sonst false
     */
    public function isLocked(): bool
    {
        $timeout = (int) Config::get('locking.timeout');
        $lock = $this->lockRecord;

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

        return $lock->locked_by !== \Illuminate\Support\Facades\Auth::id();
    }

    /**
     * Liefert den sperrenden Benutzer oder null, falls keine Sperre existiert.
     *
     * @return mixed|null
     */
    public function lockedBy()
    {
        return $this->lockRecord ? $this->lockRecord->user : null;
    }

    /**
     * MorphOne-Relation zum Lock-Datensatz.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function lockRecord()
    {
        return $this->morphOne(Lock::class, 'lockable');
    }

    /**
     * Interne Helper-Methode, um optionale UI-Messages zu triggern.
     * Implementiere `handleLockMessage(string $message)` im Model, um diese zu verarbeiten.
     *
     * @param string $message
     * @return void
     */
    protected function fireLockMessage(string $message): void
    {
        if (method_exists($this, 'handleLockMessage')) {
            $this->handleLockMessage($message);
        }
    }
}
