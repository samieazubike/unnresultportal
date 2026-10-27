<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The courses an administrator pre-selects for one department and level in a
 * given session and semester. Saving an offering registers every matching
 * student for those courses; see CourseOfferingService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dept_id')->constrained('departments')->cascadeOnDelete();
            $table->unsignedSmallInteger('level');   // 100 to 600
            $table->string('session', 9);             // '2025/2026'
            $table->unsignedTinyInteger('semester');  // 1 or 2
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();

            $table->unique(['dept_id', 'level', 'session', 'semester', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};
