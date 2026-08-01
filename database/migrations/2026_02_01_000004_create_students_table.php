<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * README §4: students(id, user_id → users, reg_number UNIQUE,
 *                     dept_id → departments, entry_year)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('reg_number')->unique();
            $table->foreignId('dept_id')->constrained('departments')->cascadeOnDelete();
            $table->unsignedSmallInteger('entry_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
