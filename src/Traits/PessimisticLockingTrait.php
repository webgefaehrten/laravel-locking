<?php

namespace Webgefaehrten\Locking\Traits;

use Webgefaehrten\Locking\Models\Lock;
use Webgefaehrten\Locking\Events\ModelLocked;
use Webgefaehrten\Locking\Events\ModelUnlocked;
use Illuminate\Support\Facades\Auth;

trait PessimisticLockingTrait
{
    public function lock(string $domain): bool
    {
        $timeout = config('locking.timeout');
        $existingLock = $this->lockRecord;

        if ($existingLock && (
            $existingLock->locked_at->diffInMinutes(now()) >= $timeout
            || $existingLock->locked_at->diffInHours(now()) >= 1
        )) {
            $existingLock->delete();
        }

        if ($this->lockRecord && $this->lockRecord->locked_by !== Auth::id()) {
            $this->fireLockMessage("Eintrag #{$this->id} ist bereits von einem anderen Benutzer gesperrt.");
            return false;
        }

        $lock = $this->lockRecord()->updateOrCreate(
            ['lockable_type' => static::class, 'lockable_id' => $this->id],
            ['locked_by' => Auth::id(), 'locked_at' => now()]
        );

        broadcast(new ModelLocked($lock, $domain))->toOthers();
        return true;
    }

    public function unlock(string $domain): void
    {
        $lock = $this->lockRecord;
        if ($lock && $lock->locked_by === Auth::id()) {
            $lock->delete();
            broadcast(new ModelUnlocked($domain, static::class, $this->id))->toOthers();
        }
    }

    public function isLocked(): bool
    {
        $timeout = config('locking.timeout');
        $lock = $this->lockRecord;

        if (!$lock) {
            return false;
        }

        if (
            $lock->locked_at->diffInMinutes(now()) >= $timeout
            || $lock->locked_at->diffInHours(now()) >= 1
        ) {
            $lock->delete();
            return false;
        }

        return $lock->locked_by !== Auth::id();
    }

    public function lockedBy()
    {
        return $this->lockRecord ? $this->lockRecord->user : null;
    }

    public function lockRecord()
    {
        return $this->morphOne(Lock::class, 'lockable');
    }

    protected function fireLockMessage(string $message): void
    {
        if (method_exists($this, 'handleLockMessage')) {
            $this->handleLockMessage($message);
        }
    }
}
