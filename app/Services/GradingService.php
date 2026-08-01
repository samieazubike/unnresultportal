<?php

namespace App\Services;

use App\Enums\ResultStatus;
use App\Models\Registration;
use App\Models\Result;
use App\Models\User;
use App\Support\Grading;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies the grading scale to stored results.
 *
 * Grade and grade point are always computed here from the scores. They are
 * never accepted from input (README §10), and the boundaries themselves live
 * only in App\Support\Grading.
 */
class GradingService
{
    public function __construct(private readonly ResultIntegrityService $integrity) {}

    /**
     * Record CA and exam scores against a registration, deriving the total,
     * grade and grade point, and refreshing the integrity signature.
     *
     * @throws RuntimeException when the sheet is no longer open for editing
     */
    public function record(Registration $registration, float $caScore, float $examScore, ?User $actor = null): Result
    {
        return DB::transaction(function () use ($registration, $caScore, $examScore, $actor) {
            $result = Result::query()->firstOrNew(['registration_id' => $registration->getKey()]);

            if ($result->exists && ! $result->allowsScoreEditing()) {
                throw new RuntimeException(
                    "Scores for this result are locked: it is already {$result->status->value}."
                );
            }

            $result->fill($this->scoreAttributes($caScore, $examScore));

            if (! $result->exists) {
                $result->status = ResultStatus::Draft;
            }

            $result->save();

            $this->integrity->sign($result, $actor);

            return $result;
        });
    }

    /**
     * Derive every score-dependent column from the two inputs.
     *
     * @return array{ca_score: float, exam_score: float, total_score: float, grade: string, grade_point: int}
     */
    public function scoreAttributes(float $caScore, float $examScore): array
    {
        $total = Grading::total($caScore, $examScore);
        $band = Grading::forScore($total);

        return [
            'ca_score' => $caScore,
            'exam_score' => $examScore,
            'total_score' => $total,
            'grade' => $band['grade'],
            'grade_point' => $band['grade_point'],
        ];
    }

    /**
     * Validation bounds for score input, drawn from the single source of
     * truth so the rules cannot drift from the scale.
     *
     * @return array<string, string>
     */
    public function scoreValidationRules(): array
    {
        return [
            'ca_score' => 'required|numeric|min:0|max:'.Grading::MAX_CA_SCORE,
            'exam_score' => 'required|numeric|min:0|max:'.Grading::MAX_EXAM_SCORE,
        ];
    }
}
