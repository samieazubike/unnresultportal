<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * README §4: courses(id, course_code UNIQUE, course_title, credit_units,
 *                    dept_id → departments, lecturer_id → users NULL)
 *
 * lecturer_id is retained exactly as documented and denotes the course HEAD.
 * The wider teaching team lives in the course_lecturer pivot; see the
 * following migration, which also adds the constraint tying the two together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('course_code')->unique();
            $table->string('course_title');
            $table->unsignedTinyInteger('credit_units');
            $table->foreignId('dept_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
