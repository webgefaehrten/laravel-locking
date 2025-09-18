<?php

namespace Webgefaehrten\Locking\Traits;

use Exception;
use Illuminate\Support\Carbon;

/**
 * DE: Trait für optimistisches Locking auf Basis von Eloquent-Timestamps.
 *
 * Wirkprinzip:
 *  - Vor jedem Update wird geprüft, ob sich der Wert von `updated_at` seit dem Laden verändert hat.
 *  - Bei Abweichung wird ein Konflikt angenommen und eine Exception geworfen.
 *
 * Voraussetzungen:
 *  - Eloquent Timestamps sind aktiv (Feld: `updated_at`).
 *
 * Optional:
 *  - Definiere auf deinem Model eine Methode `handleConflictMessage(string $message): void`,
 *    um eine benutzerdefinierte Meldung (z. B. Flash/Toast) auszugeben, bevor die Exception geworfen wird.
 *
 * EN: Optimistic locking trait using Eloquent timestamps. Checks `updated_at` on update and throws
 *     an exception when it changed meanwhile. Optionally calls `handleConflictMessage($msg)` on the model.
 */
trait OptimisticLockingTrait
{
    /**
     * Registriert den Updating-Hook für das Modell.
     *
     * Wirft bei Konflikt eine Exception und ruft optional `handleConflictMessage($message)` auf dem
     * Modell auf, wenn vorhanden.
     *
     * @return void
     * @throws Exception
     */
    protected static function bootOptimisticLockingTrait()
    {
        static::updating(function ($model) {
            $updatedAtColumn = $model->getUpdatedAtColumn();
            $originalUpdatedAt = $model->getOriginal($updatedAtColumn);

            if (!$originalUpdatedAt) {
                return;
            }

            // Aktuellen DB-Wert laden (ohne Scopes), um Race-Conditions zu erkennen
            $currentUpdatedAt = $model->newQueryWithoutScopes()
                ->whereKey($model->getKey())
                ->value($updatedAtColumn);

            if (!$currentUpdatedAt) {
                return;
            }

            $original = $originalUpdatedAt instanceof Carbon
                ? $originalUpdatedAt
                : Carbon::parse((string) $originalUpdatedAt);
            $current = $currentUpdatedAt instanceof Carbon
                ? $currentUpdatedAt
                : Carbon::parse((string) $currentUpdatedAt);

            if ($current->ne($original)) {
                if (method_exists($model, 'handleConflictMessage')) {
                    $model->handleConflictMessage(
                        "Dieser Datensatz wurde bereits von jemand anderem geändert. Bitte Seite neu laden."
                    );
                }

                throw new Exception(
                    "Dieser Datensatz wurde bereits von jemand anderem geändert. Bitte Seite neu laden."
                );
            }
        });
    }
}
