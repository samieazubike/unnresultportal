<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * README §8.4: cache the generated advice against the student and session.
 *
 * facts and flags are stored alongside the phrasing so every sentence shown to
 * a student remains traceable back to the query that produced it — which is
 * the whole defensibility claim of §8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('session', 9);
            $table->jsonb('facts');
            $table->jsonb('flags');
            $table->text('body');
            $table->string('source', 20); // 'llm' or 'fallback'
            $table->timestamp('generated_at');

            $table->unique(['student_id', 'session']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
