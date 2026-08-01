<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teaching teams: a course carries two or three lecturers, one of whom is the
 * head recorded in courses.lecturer_id.
 *
 * The head must also be a member of the team. Rather than leaving that to
 * application code, a composite foreign key from courses(id, lecturer_id) to
 * course_lecturer(course_id, lecturer_id) makes the database enforce it.
 * DEFERRABLE INITIALLY DEFERRED so a course and its team can be written in
 * either order within one transaction; default MATCH SIMPLE semantics mean a
 * NULL lecturer_id is exempt, which is correct for a course with no head yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        $postgres = $this->onPostgres();

        Schema::create('course_lecturer', function (Blueprint $table) use ($postgres) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            if (! $postgres) {
                $table->unique(['course_id', 'lecturer_id']);
            }
        });

        if (! $postgres) {
            return;
        }

        // A named UNIQUE constraint rather than Laravel's unique index: the
        // composite foreign key below must reference a unique constraint.
        DB::statement('ALTER TABLE course_lecturer ADD CONSTRAINT course_lecturer_pair_unique UNIQUE (course_id, lecturer_id)');

        DB::statement(<<<'SQL'
            ALTER TABLE courses
                ADD CONSTRAINT courses_head_is_team_member
                FOREIGN KEY (id, lecturer_id)
                REFERENCES course_lecturer (course_id, lecturer_id)
                DEFERRABLE INITIALLY DEFERRED
        SQL);
    }

    public function down(): void
    {
        if ($this->onPostgres()) {
            DB::statement('ALTER TABLE courses DROP CONSTRAINT IF EXISTS courses_head_is_team_member');
        }

        Schema::dropIfExists('course_lecturer');
    }

    /**
     * The deferrable composite foreign key above is PostgreSQL-specific, and
     * PostgreSQL is what the web application and the test suite run on. The
     * desktop shell's local SQLite store never queries this table — it exists
     * only because NativePHP migrates that file on startup, so the schema has
     * to be creatable there without the constraint.
     */
    private function onPostgres(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }
};
