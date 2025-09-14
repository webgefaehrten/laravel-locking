<?php

/**
 * Konfiguration für das Locking-Paket
 * DE: Globale Einstellungen für Zeitlimits, Intervall des Aufräum-Jobs, Queue-Namen und Tenancy-Verhalten.
 * EN: Global settings for timeouts, cleanup job interval, queue name, and tenancy behavior.
 */
return [

    // DE: Anzahl der Minuten bis ein Lock automatisch abläuft (Timeout).
    // EN: Number of minutes after which a lock expires automatically (timeout).
    'timeout' => env('LOCKING_TIMEOUT', 5),

    // DE: Intervall in Minuten, in dem der Cleanup-Befehl ausgeführt wird.
    // EN: Interval in minutes at which the cleanup command is executed.
    'interval' => env('LOCKING_INTERVAL', 5),

    // DE: Name der Queue, auf der zeitgesteuerte Jobs laufen (z. B. Horizon).
    // EN: Name of the queue on which scheduled jobs run (e.g., Horizon).
    'queue' => env('LOCKING_QUEUE', 'locking'),

    /*
    |--------------------------------------------------------------------------
    | Tenancy aktivieren / Enable tenancy
    |--------------------------------------------------------------------------
    | DE: Wenn true
    |   - Channels validieren tenant()->primary_domain->domain
    |   - Cleanup läuft tenant-aware über "tenants:run"
    | Wenn false
    |   - Channels erlauben jeden authentifizierten Benutzer
    |   - Cleanup läuft zentral gegen die Hauptdatenbank
    |
    | EN: If true
    |   - Channels validate tenant()->primary_domain->domain
    |   - Cleanup runs tenant-aware via "tenants:run"
    | If false
    |   - Channels allow any authenticated user
    |   - Cleanup runs centrally against the primary database
    */
    'tenancy' => env('LOCKING_TENANCY', false),

];