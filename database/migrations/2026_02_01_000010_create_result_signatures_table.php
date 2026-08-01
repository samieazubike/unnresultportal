<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tamper-evidence for results.
 *
 * An HMAC-SHA256 over the canonical score payload, keyed by APP_KEY. It is
 * refreshed on every authorised write and frozen at approval. Verification on
 * read detects any change made outside the application — a direct UPDATE, a
 * restored backup, a manual "fix" — because whoever made it could not
 * recompute the signature without the application key.
 *
 * Kept out of the results table so the seven documented columns stay exactly
 * as Chapter Three specifies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->unique()->constrained('results')->cascadeOnDelete();
            $table->string('integrity_hash', 64);
            $table->string('algo', 20)->default('hmac-sha256');
            $table->boolean('frozen')->default(false);
            $table->timestamp('signed_at');
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_signatures');
    }
};
