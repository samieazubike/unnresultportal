<?php

namespace Database\Factories;

use App\Enums\ResultStatus;
use App\Models\Registration;
use App\Models\Result;
use App\Models\User;
use App\Support\Grading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Result>
 */
class ResultFactory extends Factory
{
    /**
     * Grade and grade point are always derived from the scores through
     * Grading, never invented here — the factory must not become a second
     * place the boundaries live.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ca = fake()->numberBetween(0, Grading::MAX_CA_SCORE);
        $exam = fake()->numberBetween(0, Grading::MAX_EXAM_SCORE);

        return [
            'registration_id' => Registration::factory(),
            ...$this->scoreAttributes($ca, $exam),
            'status' => ResultStatus::Draft,
            'published_by' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreAttributes(float $ca, float $exam): array
    {
        $total = Grading::total($ca, $exam);
        $band = Grading::forScore($total);

        return [
            'ca_score' => $ca,
            'exam_score' => $exam,
            'total_score' => $total,
            'grade' => $band['grade'],
            'grade_point' => $band['grade_point'],
        ];
    }

    /**
     * Force a specific total score, splitting it across CA and exam within
     * their documented maxima.
     */
    public function scoring(float $total): static
    {
        $ca = min(Grading::MAX_CA_SCORE, round($total * 0.3, 2));
        $exam = round($total - $ca, 2);

        return $this->state(fn (array $attributes) => $this->scoreAttributes($ca, $exam));
    }

    public function withGrade(string $grade): static
    {
        $total = match (strtoupper($grade)) {
            'A' => 75, 'B' => 65, 'C' => 55, 'D' => 47, 'E' => 42, default => 30,
        };

        return $this->scoring($total);
    }

    public function status(ResultStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function draft(): static
    {
        return $this->status(ResultStatus::Draft);
    }

    public function submitted(): static
    {
        return $this->status(ResultStatus::Submitted);
    }

    public function approved(): static
    {
        return $this->status(ResultStatus::Approved);
    }

    public function published(?User $publisher = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ResultStatus::Published,
            'published_by' => $publisher?->getKey() ?? User::factory()->examOfficer(),
        ]);
    }
}
