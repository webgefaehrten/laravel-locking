<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lock Timeout (in Minuten)
    |--------------------------------------------------------------------------
    | Wie lange eine Sperre gültig bleibt, bevor sie automatisch als abgelaufen
    | gilt und entfernt werden kann.
    */
    'timeout' => env('LOCKING_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Cleanup Interval
    |--------------------------------------------------------------------------
    | In welchem Intervall der Cleanup-Command laufen soll.
    | 1, 5, 10, 15, 30 Minuten oder "hourly".
    */
    'interval' => env('LOCKING_INTERVAL', 5),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | Queue-Name für den Cleanup-Job.
    */
    'queue' => env('LOCKING_QUEUE', 'locking'),

    /*
    |--------------------------------------------------------------------------
    | Tenancy Integration
    |--------------------------------------------------------------------------
    | Ob Stancl Tenancy verwendet wird.
    | Wenn true → Domain wird über tenant()->primary_domain->domain ermittelt.
    */
    'tenancy' => env('LOCKING_TENANCY', true),

    /*
    |--------------------------------------------------------------------------
    | Broadcast to Self
    |--------------------------------------------------------------------------
    | Standardmäßig broadcastet das Paket Locks nur mit ->toOthers(),
    | d. h. der Client, der das Lock setzt, empfängt sein eigenes Event nicht.
    | 
    | Wenn du auch im eigenen Browserfenster Feedback brauchst
    | (z. B. für Debugging oder direkte UI-Reaktionen), kannst du das aktivieren.
    */
    'broadcast_self' => env('LOCKING_BROADCAST_SELF', false),

];