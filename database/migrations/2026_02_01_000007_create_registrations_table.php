<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * README §4: registrations(id, student_id → students, course_id → courses,
 *                          session, semester)
 *            UNIQUE(student_id, course_id, session, semester)
 *
 * A student is not linked directly to a course. The registration sits between
 * them, so a retake of the same course code in a later session is a separate
 * unambiguous record carrying its own result.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('session', 9);          // '2025/2026'
            $table->unsignedTinyInteger('semester'); // 1 or 2

            $table->unique(['student_id', 'course_id', 'session', 'semester']);

            // The §7 aggregates group and filter on these.
            $table->index(['session', 'semester']);
            $table->index(['course_id', 'session']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
