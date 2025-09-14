<?php

namespace Webgefaehrten\Locking\Traits;

use Webgefaehrten\Locking\Models\Lock;
use Webgefaehrten\Locking\Events\ModelLocked;
use Webgefaehrten\Locking\Events\ModelUnlocked;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

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
     * @return bool  true, wenn gesperrt wurde; false, wenn bereits fremd gesperrt
     */
    public function lock(): bool
    {
        $timeout = (int) Config::get('locking.timeout');
        $domain = $this->resolveDomain();
        $existingLock = $this->lockRecord;

        if ($existingLock && (
            $existingLock->locked_at->diffInMinutes(Carbon::now()) >= $timeout
            || $existingLock->locked_at->diffInHours(Carbon::now()) >= 1
        )) {
            $existingLock->delete();
        }

        if ($this->lockRecord && $this->lockRecord->locked_by !== Auth::id()) {
            $this->fireLockMessage("Eintrag #{$this->id} ist bereits von einem anderen Benutzer gesperrt.");
            return false;
        }

        $lock = $this->lockRecord()->updateOrCreate(
            ['lockable_type' => static::class, 'lockable_id' => $this->id],
            ['locked_by' => Auth::id(), 'locked_at' => Carbon::now()]
        );

        Event::dispatch(new ModelLocked($lock, $domain));
        return true;
    }

    /**
     * Entfernt die eigene Sperre und broadcastet `ModelUnlocked`.
     *
     * @return void
     */
    public function unlock(): void
    {
        $domain = $this->resolveDomain();
        $lock = $this->lockRecord;
        if ($lock && $lock->locked_by === Auth::id()) {
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

        return $lock->locked_by !== Auth::id();
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

    /**
     * Ermittelt die Broadcast-Domain. Bei aktivierter Tenancy wird die Tenant-Primary-Domain genutzt.
     * Fallback ist "default".
     *
     * @return string
     */
    protected function resolveDomain(): string
    {
        if ((bool) Config::get('locking.tenancy', false)) {
            // Stancl Tenancy Helper sicher verwenden, falls vorhanden
            if (function_exists('tenant')) {
                $tenant = \tenant();
                if ($tenant && isset($tenant->primary_domain) && isset($tenant->primary_domain->domain)) {
                    return (string) $tenant->primary_domain->domain;
                }
            }
        }

        return 'default';
    }
}
