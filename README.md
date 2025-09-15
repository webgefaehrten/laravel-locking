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
- ⚙️ **Scheduler + Horizon Queue** für Lock-Cleanup
- 💻 **Einfache Integration über Traits**

---

## ⚙️ Installation

### 1️⃣ Paket installieren
```bash
composer require webgefaehrten/laravel-locking
```

Dadurch wird automatisch `php artisan locking:install` ausgeführt  
(*bei Bedarf `--no-scripts` nutzen, um es manuell zu machen*)

---

### 2️⃣ Installation zentral (Standard)
```bash
php artisan locking:install
```

- Config: `config/locking.php`
- Migrationen: `database/migrations`
- Channels: `routes/channels.php`
- Führt `php artisan migrate` direkt aus

---

### 3️⃣ Installation tenant-aware (Stancl Tenancy)
```bash
php artisan locking:install --tenancy
php artisan tenants:migrate
```

- Migrationen werden in `database/migrations/tenant` kopiert
- Du führst sie dann tenantweise mit `tenants:migrate` aus
- In `config/locking.php`:  
  ```php
  'tenancy' => true
  ```

---

## ⚙️ Konfiguration (`config/locking.php`)
```php
return [
    'timeout' => env('LOCKING_TIMEOUT', 5),        // Minuten bis ein Lock verfällt
    'single_per_table' => env('LOCKING_SINGLE_PER_TABLE', true), // Nur ein Datensatz pro Tabelle/User
    'interval' => env('LOCKING_INTERVAL', 5),      // Cleanup-Intervall in Minuten
    'queue' => env('LOCKING_QUEUE', 'locking'),    // Queue für Cleanup-Jobs
    'tenancy' => env('LOCKING_TENANCY', false),    // Multi-Tenancy aktivieren
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

---

### Optimistic Locking (Änderungskollision verhindern)

```php
use Webgefaehrten\Locking\Traits\OptimisticLockingTrait;

class Contact extends Model {
    use OptimisticLockingTrait;
}
```

**Wirkung:**
- Beim `update()` prüft das Trait, ob `updated_at` noch dem Wert beim Laden entspricht
- Wenn nicht → wirft Exception  
  („Dieser Datensatz wurde bereits von jemand anderem geändert“)

---

## 📡 Broadcasting & Livewire

- Beim Sperren (`lock`) wird ein `ModelLocked` Event via PrivateChannel `locks.{domain}` gebroadcastet
- Beim Freigeben (`unlock`) ein `ModelUnlocked` Event
- Andere Nutzer können dies per Livewire-Listener empfangen und z. B. mit **Flux Toast** anzeigen

Hinweis (Queue + Tenancy):
- Das Paket broadcastet Events über einen Queue-Job (`BroadcastLockEvent`).
- Wenn `tenancy=true`, wird im Job automatisch der Tenant-Kontext initialisiert, damit die Events im richtigen Mandantenkanal landen.
- Stelle sicher, dass ein Queue-Worker/Horizon läuft und die Queue aus `config('locking.queue', 'locking')` verarbeitet.

**Channel Auth (`routes/channels.php`):**
```php
Broadcast::channel('locks.{domain}', function ($user, $domain) {
    if (! $user) return false;

    if (config('locking.tenancy')) {
        return $user->tenant && $user->tenant->primary_domain->domain === $domain;
    }

    return true;
});
```

Hinweis zur Domain-Ermittlung:
- Wenn `config('locking.tenancy') === true`, ermittelt das Trait die Domain automatisch aus `tenant()->primary_domain->domain` und nutzt damit garantiert den Channel `locks.{tenantDomain}`.
- Wenn Tenancy deaktiviert ist, wird standardmäßig die Domain `default` verwendet.

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

## 🕒 Scheduler & Horizon Queue

Das Paket registriert den Cleanup automatisch im Scheduler:

- Wenn `tenancy=false`:
  ```bash
  locking:cleanup
  ```
- Wenn `tenancy=true`:
  ```bash
  tenants:run locking:cleanup
  ```

Alle Cleanup-Jobs laufen in der Queue `locking`.

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
