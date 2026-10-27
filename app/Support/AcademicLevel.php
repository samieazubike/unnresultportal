<?php

namespace App\Support;

use App\Models\Student;

/**
 * A student's level (100L to 600L) in a given session.
 *
 * The roster records only the year a student entered, so the level is
 * derived from it rather than stored: a student who entered in 2024 is in
 * 100L in 2024/2025 and in 200L in 2025/2026. Students past their final
 * year carry on counting upward and simply match no course offering.
 */
final class AcademicLevel
{
    /**
     * The levels an administrator can offer courses for. Most programmes end
     * at 400L or 500L; some run to 600L.
     *
     * @var list<int>
     */
    public const LEVELS = [100, 200, 300, 400, 500, 600];

    public static function forStudent(Student $student, string $session): int
    {
        return max(100, (self::sessionStartYear($session) - $student->entry_year + 1) * 100);
    }

    /**
     * The entry year of the cohort that sits at this level in this session.
     */
    public static function entryYearFor(int $level, string $session): int
    {
        return self::sessionStartYear($session) - intdiv($level, 100) + 1;
    }

    /**
     * The first year of a '2025/2026' style session.
     */
    public static function sessionStartYear(string $session): int
    {
        return (int) explode('/', $session)[0];
    }
}
