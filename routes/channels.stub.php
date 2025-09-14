<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Locking Broadcast Channel / Sperren-Broadcast-Kanal
|--------------------------------------------------------------------------
|
| DE: Authentifizierung für den privaten Channel "locks.{domain}".
|   - Tenancy AUS: Jeder authentifizierte Benutzer darf Events empfangen.
|   - Tenancy AN: Es wird geprüft, ob der Benutzer zum Tenant gehört,
|                 dessen primary_domain->domain der {domain} entspricht.
|
| EN: Authentication for the private channel "locks.{domain}".
|   - Tenancy OFF: Any authenticated user may receive events.
|   - Tenancy ON: Validates that the user belongs to the tenant whose
|                 primary_domain->domain matches the provided {domain}.
|
*/
if (config('locking.tenancy')) {
    // Multi-Tenancy aktiv → tenant.{domain}.locks
    Broadcast::channel('tenant.{domain}.locks', function ($user, $domain) {
        if (! $user) return false;

        return $user->tenant
            && $user->tenant->primary_domain->domain === $domain;
    });
} else {
    // Keine Tenancy → locks.{domain}
    Broadcast::channel('locks.{domain}', function ($user, $domain) {
        return (bool) $user;
    });
}