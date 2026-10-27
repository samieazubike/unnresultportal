<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether students may add or drop courses for a session and semester. An
 * administrator opens and closes each window by hand; a missing row means
 * closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_windows', function (Blueprint $table) {
            $table->id();
            $table->string('session', 9);
            $table->unsignedTinyInteger('semester');
            $table->boolean('is_open')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['session', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_windows');
    }
};
