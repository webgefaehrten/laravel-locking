<?php

namespace Webgefaehrten\Locking\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DE: Repräsentiert eine Sperre für eine beliebige Model-Instanz.
 * EN: Represents a lock for any model instance.
 */
class Lock extends Model
{
    protected $fillable = ['locked_by', 'locked_at'];

    /**
     * DE: Polymorphe Beziehung zur gesperrten Model-Instanz.
     * EN: Polymorphic relation to the locked model instance.
     */
    public function lockable()
    {
        return $this->morphTo();
    }

    /**
     * DE: Benutzer, der die Sperre gesetzt hat.
     * EN: User who created the lock.
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'locked_by');
    }
}
