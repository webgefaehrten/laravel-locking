<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: erstellt die Tabelle "locks" für pessimistische/optimistische Sperren.
 * DE: Verwaltet Sperr-Einträge pro Model-Instanz inkl. Benutzer und Zeitstempel.
 * EN: Creates the "locks" table to handle locking per model instance including user and timestamp.
 */
return new class extends Migration {
    /**
     * DE: Tabelle anlegen mit Morph-Relation, Benutzer-Referenz und Zeitstempeln.
     * EN: Create table with morph relation, user reference and timestamps.
     */
    public function up(): void
    {
        Schema::create('locks', function (Blueprint $table) {
            $table->id();
            $table->morphs('lockable');
            $table->foreignId('locked_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('locked_at');
            $table->timestamps();
        });
    }

    /**
     * DE: Tabelle wieder entfernen.
     * EN: Drop the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('locks');
    }
};
