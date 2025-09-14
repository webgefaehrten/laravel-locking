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
    'interval' => env('LOCKING_INTERVAL', 5),      // Cleanup-Intervall in Minuten
    'queue' => env('LOCKING_QUEUE', 'locking'),    // Queue für Cleanup-Jobs
    'tenancy' => env('LOCKING_TENANCY', false),    // Multi-Tenancy aktivieren
];
```

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
if (! $tour->lock('lehmann')) {
    return back()->with('error', 'Tour wird gerade von einem anderen Benutzer bearbeitet.');
}

// Beim Speichern / Abbrechen entsperren
$tour->unlock('lehmann');
```

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

## Messages
```
Tour::setLockMessageHandler(function ($message, $model) {
    session()->flash('error', $message);
});

Contact::setConflictMessageHandler(function ($message, $model) {
    session()->flash('error', $message);
});

Tour::setLockMessageHandler(fn($msg) => $this->dispatch('flux-toast', [
    'title' => $msg, 'variant' => 'warning'
]));

```

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

In deiner Livewire-Komponente:

```php
protected $listeners = ['echo-private:locks.'.tenant()->primary_domain->domain.',ModelLocked' => 'onLocked'];

public function onLocked($data)
{
    $this->dispatch('flux-toast', [
        'title' => $data['message'],
        'variant' => 'warning',
    ]);
}
```

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
