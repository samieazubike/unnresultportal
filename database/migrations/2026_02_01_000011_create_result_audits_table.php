<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail: every score write and every status transition,
 * with the actor, the old and new values, and the originating IP.
 *
 * Nothing in the application updates or deletes these rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained('results')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['result_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_audits');
    }
};
