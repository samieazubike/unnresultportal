<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * README §4: faculties(id, faculty_name UNIQUE)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $table) {
            $table->id();
            $table->string('faculty_name')->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculties');
    }
};
