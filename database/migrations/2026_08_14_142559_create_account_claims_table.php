<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One in-progress attempt to claim a roster row.
 *
 * The code itself is never stored — only its hash — and the row keeps the
 * email, IP and attempt count of every attempt, successful or not, so an
 * administrator can see who has been probing which registration numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->index()->constrained('students')->cascadeOnDelete();
            $table->string('email');
            $table->string('code_hash');
            $table->timestamp('expires_at')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_claims');
    }
};
