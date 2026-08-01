<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * The UNN five-point grading scale (README §5).
 *
 * This is the ONLY place the boundaries appear. README §16 names
 * "hardcoding the grade boundaries in more than one file" as something that
 * breaks the defence, so nothing else in the application may repeat them —
 * not a controller, not a seeder, not a Blade template, not a React component.
 *
 *   ca_score is out of 30, exam_score out of 70, total_score out of 100.
 *
 *   | Total score  | Grade | Grade point |
 *   |--------------|-------|-------------|
 *   | 70 and above | A     | 5           |
 *   | 60 to 69     | B     | 4           |
 *   | 50 to 59     | C     | 3           |
 *   | 45 to 49     | D     | 2           |
 *   | 40 to 44     | E     | 1           |
 *   | Below 40     | F     | 0           |
 */
final class Grading
{
    public const MAX_CA_SCORE = 30;

    public const MAX_EXAM_SCORE = 70;

    public const MAX_TOTAL_SCORE = 100;

    /**
     * Lower bound of each band, highest first. Read top to bottom, the first
     * band whose minimum the score reaches wins.
     *
     * @var list<array{min: int, grade: string, grade_point: int}>
     */
    private const SCALE = [
        ['min' => 70, 'grade' => 'A', 'grade_point' => 5],
        ['min' => 60, 'grade' => 'B', 'grade_point' => 4],
        ['min' => 50, 'grade' => 'C', 'grade_point' => 3],
        ['min' => 45, 'grade' => 'D', 'grade_point' => 2],
        ['min' => 40, 'grade' => 'E', 'grade_point' => 1],
        ['min' => 0, 'grade' => 'F', 'grade_point' => 0],
    ];

    /**
     * Resolve a total score to its grade and grade point.
     *
     * @return array{grade: string, grade_point: int}
     */
    public static function forScore(float $total): array
    {
        if ($total < 0 || $total > self::MAX_TOTAL_SCORE) {
            throw new InvalidArgumentException(
                "Total score {$total} is outside the range 0 to ".self::MAX_TOTAL_SCORE.'.'
            );
        }

        foreach (self::SCALE as $band) {
            if ($total >= $band['min']) {
                return ['grade' => $band['grade'], 'grade_point' => $band['grade_point']];
            }
        }

        // Unreachable: the final band has a minimum of 0.
        throw new InvalidArgumentException("No grade band matched score {$total}.");
    }

    public static function grade(float $total): string
    {
        return self::forScore($total)['grade'];
    }

    public static function gradePoint(float $total): int
    {
        return self::forScore($total)['grade_point'];
    }

    /**
     * total_score = ca_score + exam_score, out of 100.
     */
    public static function total(float $caScore, float $examScore): float
    {
        return round($caScore + $examScore, 2);
    }

    public static function isFailing(string $grade): bool
    {
        return $grade === 'F';
    }

    /**
     * Degree classification for a final CGPA, on the same five-point scale.
     */
    public static function classification(float $cgpa): string
    {
        return match (true) {
            $cgpa >= 4.50 => 'First Class Honours',
            $cgpa >= 3.50 => 'Second Class Honours (Upper Division)',
            $cgpa >= 2.40 => 'Second Class Honours (Lower Division)',
            $cgpa >= 1.50 => 'Third Class Honours',
            $cgpa >= 1.00 => 'Pass',
            default => 'Fail',
        };
    }

    /**
     * Every grade letter, highest band first. Useful for chart axes and for
     * filling gaps where a grade has no rows.
     *
     * @return list<string>
     */
    public static function grades(): array
    {
        return array_column(self::SCALE, 'grade');
    }
}
