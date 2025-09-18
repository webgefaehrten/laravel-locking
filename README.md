# 📦 Laravel Locking  
**`webgefaehrten/laravel-locking`**  
> Universelles Locking-System für Laravel mit Pessimistic & Optimistic Locking, Multi-Tenancy-Unterstützung, automatischer Timeout-Aufhebung und WebSocket-Broadcasting.

---

## 🚀 Features
- 🔒 **Pessimistic Locking** (Ein Datensatz exklusiv pro Nutzer)
- ⚡ **Optimistic Locking** (Änderung nur erlaubt, wenn sich der Datensatz nicht geändert hat)
- ⌛ **Automatisches Unlock nach Timeout**
- 🌐 **Multi-Tenancy (Stancl Tenancy)** optional integriert
- 📡 **Broadcasting** mit Laravel Echo / Livewire (Realtime Feedback für andere Nutzer)
- ⏱️ **Automatischer Scheduler** für Lock-Cleanup (kein Queue-Zwang)
- 💻 **Einfache Integration über Traits & Middleware**

---

## ⚙️ Installation

### 1️⃣ Paket installieren
```bash
composer require webgefaehrten/laravel-locking
```

### 2️⃣ Quick-Install ausführen
Veröffentlicht Konfiguration, Broadcast-Channels und Migrationen. Mit `--tenancy` werden Migrationen nach `database/migrations/tenant` kopiert.
```bash
php artisan locking:install           # zentral
php artisan locking:install --tenancy # tenant-aware
```

### 3️⃣ Migration ausführen
- Zentral (ohne Tenancy):
```bash
php artisan migrate
```

- Tenant-aware (Stancl):
```bash
php artisan tenants:migrate
```

Optional: Manuell publishen (Alternative zu Schritt 2)
```bash
php artisan vendor:publish --tag=locking-config
php artisan vendor:publish --tag=locking-channels
php artisan vendor:publish --tag=locking-migrations
```

In `config/locking.php` ggf. Tenancy aktivieren:
```php
'tenancy' => true
```

### 🔄 Update / Upgrade
So aktualisierst du das Paket und übernimmst Änderungen sicher:
```bash
composer update webgefaehrten/laravel-locking

# Falls es Config/Channel-Änderungen gab
php artisan vendor:publish --tag=locking-config --force
php artisan vendor:publish --tag=locking-channels --force

# Neue Migrationen übernehmen
php artisan vendor:publish --tag=locking-migrations

# Migrationen ausführen (zentral oder tenant-aware)
php artisan migrate
# oder
php artisan tenants:migrate

# Caches leeren (empfohlen nach Updates)
php artisan optimize:clear
```

---

## ⚙️ Konfiguration (`config/locking.php`)
```php
return [
    'timeout' => env('LOCKING_TIMEOUT', 5),              // Minuten bis ein Lock verfällt
    'single_per_table' => env('LOCKING_SINGLE_PER_TABLE', true), // Nur ein Datensatz pro Tabelle/User
    'interval' => env('LOCKING_INTERVAL', 5),            // Cleanup-Intervall in Minuten
    'tenancy' => env('LOCKING_TENANCY', false),          // Multi-Tenancy aktivieren
    'broadcast_self' => env('LOCKING_BROADCAST_SELF', false), // Eigene Events empfangen
    // optional reserviert: 'queue' => env('LOCKING_QUEUE', 'default'),
];
```

Hinweis Migration (Tenant-sicher):
- Die Spalte `locked_by` referenziert nicht per Foreign-Key die `users`-Tabelle, um Probleme in Tenancy-Deployments (separate DB, abweichende User-Tabellen) zu vermeiden. Wenn du zentral ohne Tenancy arbeitest und einen FK willst, ergänze ihn in deinem Projekt.

---

## 🧩 Verwendung

### Pessimistic Locking (exklusive Bearbeitung)

```php
use Webgefaehrten\Locking\Traits\PessimisticLockingTrait;

class Tour extends Model {
    use PessimisticLockingTrait;
}
```

**Beispiel:**
```php
$tour = Tour::find($id);

// Lock setzen (gibt false zurück, wenn schon von anderem User gelockt)
// Hinweis: Domain muss nicht übergeben werden. Bei Tenancy
// wird automatisch tenant()->primary_domain->domain verwendet.
if (! $tour->lock()) {
    return back()->with('error', 'Tour wird gerade von einem anderen Benutzer bearbeitet.');
}

// Beim Speichern / Abbrechen entsperren
$tour->unlock();
```

Hinweis: Wenn `single_per_table=true` (Standard), wird beim Sperren eines Datensatzes automatisch jede andere eigene Sperre derselben Tabelle gelöst. So kann ein Nutzer nicht zwei Datensätze derselben Tabelle parallel bearbeiten. Für jede gelöste Sperre wird ein `ModelUnlocked` Event gebroadcastet.

**Nützliche Helfer:**
```php
$tour->isLocked();     // bool
$tour->lockedBy();     // User-Objekt
```

### Usage mit Middleware (empfohlen)

Sperre direkt beim Betreten der Bearbeitungsseite setzen. Wichtig: Tenancy-Middleware muss vorher laufen und implizites Model-Binding nutzen.

```php
// routes/web.php (Beispiel Stancl v4)
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'web','auth',
    App\Http\Middleware\CheckLock::class.':tour', // Parametername des Modells
])->group(function () {
    Route::get('/tours/{tour}/edit', [TourController::class, 'edit']);
});
```

Du kannst die Paket-Middleware direkt verwenden: `locking.check:{param}`

```php
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'web','auth',
    'locking.check:tour', // Paket-Middleware + Param-Name
])->group(function () {
    Route::get('/tours/{tour}/edit', [TourController::class, 'edit']);
});
```

---

### Optimistic Locking (Änderungskollision verhindern)

```php
use Webgefaehrten\Locking\Traits\OptimisticLockingTrait;

class Contact extends Model {
    use OptimisticLockingTrait;
}
```

**Wirkung:**
- Beim `update()` vergleicht das Trait den zur Ladezeit gemerkten `updated_at`-Wert mit dem aktuellen DB-Wert.
- Wenn abweichend → Exception („Dieser Datensatz wurde bereits von jemand anderem geändert“)

**Empfohlene Middleware-Nutzung (Form-Schutz):**

Füge vor schreibenden Requests zusätzlich die Optimistic-Middleware ein. Diese vergleicht den im Formular gesendeten Zeitstempel mit der aktuellen DB.

```php
// routes/web.php
Route::middleware(['web','auth', 'locking.optimistic:contact,contact_version'])
    ->put('/contacts/{contact}', [ContactController::class, 'update']);
```

Im Formular gibst du den zur Ladezeit vorhandenen `updated_at`-Wert als Hidden-Feld mit:

```blade
<form method="POST" action="{{ route('contacts.update', $contact) }}">
    @method('PUT')
    @csrf
    <input type="hidden" name="contact_version" value="{{ optional($contact->updated_at)->toISOString() }}" />
    <!-- Felder ... -->
    <button type="submit">Speichern</button>
  </form>
```

Ohne expliziten zweiten Parameter erwartet die Middleware standardmäßig `<param>_version`, z. B. `contact_version`.

---

## 📡 Broadcasting & Livewire

- Sperren (`lock`) sendet `ModelLocked`
- Freigeben (`unlock`) sendet `ModelUnlocked`
- Broadcasting erfolgt synchron im Request (kein Queue-Zwang)

Channel-Namen (Client-Sicht):
- Tenancy aktiv: `private-tenant.{domain}.locks` (zusätzlich kompatibel: `private-tenant.{domain}.lock`)
- Ohne Tenancy: `private-locks.{domain}` (zusätzlich kompatibel: `private-lock.{domain}`)

Die passende Channel-Authentifizierung wird beim Installieren als `routes/channels.php` published. Sie verwendet automatisch die korrekten Kanalnamen (tenant-aware oder zentral).

Hinweis zur Domain-Ermittlung:
- Bei `tenancy=true` ermittelt das Trait die Domain aus `tenant()->primary_domain->domain`.
- Ohne Tenancy wird standardmäßig die Domain `default` verwendet.

---

## ⚙️ Artisan Commands

### `locking:install`
Installiert das Paket
- `--tenancy`: Migrationen werden in `database/migrations/tenant` kopiert  
- ohne Option: normale zentrale Migration

---

### `locking:cleanup`
Entfernt abgelaufene Locks

- Läuft automatisch **tenant-aware**, wenn `tenancy=true`  
  → dann:  
  ```bash
  php artisan tenants:run locking:cleanup
  ```
- Läuft **zentral**, wenn `tenancy=false`  
  → dann:  
  ```bash
  php artisan locking:cleanup
  ```

Optional:
```bash
php artisan locking:cleanup --tenants=uuid1 --tenants=uuid2
php artisan locking:cleanup --timeout=10
```

---

## 🕒 Scheduler

Der periodische Cleanup wird automatisch im Laravel Scheduler registriert. Es ist keine zusätzliche Konfiguration nötig.

- Wenn `tenancy=false`, wird regelmäßig ausgeführt:
  ```bash
  locking:cleanup --timeout={config('locking.timeout')}
  ```
- Wenn `tenancy=true`, wird tenant-aware ausgeführt:
  ```bash
  tenants:run locking:cleanup --timeout={config('locking.timeout')}
  ```

Das Intervall wird über `config('locking.interval')` gesteuert (1, 5, 10, 15, 30 Min. oder stündlich). Es ist kein Queue-Setup erforderlich.

---

## 📝 Channels

`routes/channels.php` wird beim Installieren published:

```php
Broadcast::channel('locks.{domain}', function ($user, $domain) {
    if (! $user) return false;

    if (config('locking.tenancy')) {
        return $user->tenant && $user->tenant->primary_domain->domain === $domain;
    }

    return true;
});
```

---

## Messages (optionale UI-Handler)

Du kannst in deinen Models optionale Handler-Methoden hinterlegen, um Benutzerfeedback (Flash/Toast) auszugeben:

```php
use Webgefaehrten\Locking\Traits\PessimisticLockingTrait;

class Tour extends Model
{
    use PessimisticLockingTrait;

    // Wird aufgerufen, wenn das Model bereits durch jemand anderes gesperrt ist
    public function handleLockMessage(string $message): void
    {
        session()->flash('error', $message);
        // oder: $this->dispatch('flux-toast', ['title' => $message, 'variant' => 'warning']);
    }
}
```

Für Optimistic Locking kannst du auf deinem Model optional `handleConflictMessage(string $message): void` implementieren. Diese Methode wird vor dem Werfen der Exception aufgerufen.

## ✅ Zusammenfassung

| Feature           | Zentral              | Tenancy (Stancl)        |
|------------------|----------------------|--------------------------|
| Migrationen       | `database/migrations` | `database/migrations/tenant` |
| Command Cleanup   | `locking:cleanup`    | `tenants:run locking:cleanup` |
| Domainprüfung WS  | nein                 | ja (`tenant->primary_domain->domain`) |
| Broadcasting      | ✅                   | ✅                         |
| Traits            | ✅                   | ✅                         |

---

## 🧪 Tipps für Livewire-Integration

### Livewire – Vollständige Anleitung (Lock, Check, Handler)

Damit die Sperre nicht ausläuft, sollte sie periodisch erneuert werden. Rufe `lock()` beim Start und anschließend per Polling regelmäßig auf. Gib die Sperre bei Speichern/Abbrechen frei.

```php
// app/Livewire/TourEdit.php
use Livewire\Component;
use App\Models\Tour;

class TourEdit extends Component
{
    public Tour $tour;

    public function mount(int $id): void
    {
        $this->tour = Tour::findOrFail($id);

        // (1) Prüfen, ob gesperrt
        if ($this->tour->isLocked()) {
            // gleicher User? -> Lock erneuern und weiter
            if (optional($this->tour->lockedBy())->id === auth()->id()) {
                $this->tour->lock();
            } else {
                // fremd gesperrt -> Meldung anzeigen und zurück
                $this->dispatch('flux-toast', [
                    'title' => 'Dieser Datensatz ist aktuell gesperrt.',
                    'variant' => 'warning',
                ]);
                return $this->redirectRoute('tours.index');
            }
        } else {
            // (2) Nicht gesperrt -> jetzt sperren (Race-Condition absichern)
            if (! $this->tour->lock()) {
                session()->flash('error', 'Dieser Datensatz wurde soeben von jemand anderem gesperrt.');
                return $this->redirectRoute('tours.index');
            }
        }
    }

    // (3) Polling hält die Sperre aktiv
    public function refreshLock(): void
    {
        $this->tour->lock();
    }

    public function save(): void
    {
        // Beispiel-Validierung/Speichern ...
        $this->tour->save();

        // (4) Sperre freigeben
        $this->tour->unlock();
        session()->flash('success', 'Gespeichert.');
        $this->redirectRoute('tours.index');
    }

    public function cancel(): void
    {
        $this->tour->unlock();
        $this->redirectRoute('tours.index');
    }

    // Echo-Listener für Locks im aktuellen Kontext
    public function getListeners(): array
    {
        $domain = config('locking.tenancy')
            ? tenant()->primary_domain->domain
            : 'default';

        return [
            "echo-private:locks.$domain,ModelLocked" => 'onLocked',
            "echo-private:locks.$domain,ModelUnlocked" => 'onUnlocked',
        ];
    }

    public function onLocked(array $data): void
    {
        if ($data['model_type'] === Tour::class && (int)$data['model_id'] === (int)$this->tour->id) {
            $this->dispatch('flux-toast', [
                'title' => $data['message'],
                'variant' => 'warning',
            ]);
        }
    }

    public function onUnlocked(array $data): void
    {
        if ($data['model_type'] === Tour::class && (int)$data['model_id'] === (int)$this->tour->id) {
            $this->dispatch('flux-toast', [
                'title' => $data['message'],
                'variant' => 'success',
            ]);
        }
    }
}
```

Template mit Polling (erneuert die Sperre z. B. alle 60 Sekunden):

```blade
<div wire:poll.60s="refreshLock">
    <!-- Formular / UI für die Bearbeitung -->

    <button wire:click="save">Speichern</button>
    <button wire:click="cancel">Abbrechen</button>
</div>
```

Hinweise:
- Wähle das Polling-Intervall kleiner als `config('locking.timeout')` (Standard: 5 Minuten). 60 Sekunden ist ein guter Startwert.
- `lock()` ist idempotent für den gleichen Benutzer und aktualisiert `locked_at` jedes Mal.
- `unlock()` sollte in allen Abbruch/Speicher-Flows aufgerufen werden. Optional kannst du per `beforeunload`-Handler (Beacon) zusätzlich absichern.

---

## 📜 Lizenz
MIT License

---

💡 **Hinweis**  
Wenn du Tenancy nutzt, setze unbedingt:

```php
'tenancy' => true
```

in `config/locking.php`, damit Cleanup & Channel-Auth tenantweise funktionieren.
