<?php

use App\Enums\ResultStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * README §4: results(id, registration_id → registrations UNIQUE,
 *                    ca_score, exam_score, total_score, grade, grade_point,
 *                    status, published_by → users NULL, timestamps)
 *
 * No gpa or cgpa column, deliberately. Both are computed on demand by SQL;
 * see VisualizationService. Adding one "for performance" is called out in
 * §16 as breaking the defence.
 *
 * Scores stay in plain numeric columns because the §6 and §7 aggregates read
 * grade, grade_point and total_score directly in SQL. Tamper-evidence is
 * provided by the HMAC in result_signatures instead of by encryption.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->unique()->constrained('registrations')->cascadeOnDelete();
            $table->decimal('ca_score', 5, 2)->nullable();    // out of 30
            $table->decimal('exam_score', 5, 2)->nullable();  // out of 70
            $table->decimal('total_score', 5, 2)->nullable(); // out of 100
            $table->char('grade', 1)->nullable();
            $table->unsignedTinyInteger('grade_point')->nullable();
            $table->enum('status', ResultStatus::values())->default(ResultStatus::Draft->value)->index();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Score ranges from §5, enforced at the database as well as in validation.
        DB::statement('ALTER TABLE results ADD CONSTRAINT results_ca_score_range CHECK (ca_score IS NULL OR (ca_score >= 0 AND ca_score <= 30))');
        DB::statement('ALTER TABLE results ADD CONSTRAINT results_exam_score_range CHECK (exam_score IS NULL OR (exam_score >= 0 AND exam_score <= 70))');
        DB::statement("ALTER TABLE results ADD CONSTRAINT results_grade_valid CHECK (grade IS NULL OR grade IN ('A','B','C','D','E','F'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
