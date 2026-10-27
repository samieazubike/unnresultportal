<?php

use App\Models\Student;
use App\Support\AcademicLevel;

it('derives a student level from entry year and session', function (int $entryYear, string $session, int $level) {
    expect(AcademicLevel::forStudent(new Student(['entry_year' => $entryYear]), $session))->toBe($level);
})->with([
    [2025, '2025/2026', 100],
    [2024, '2025/2026', 200],
    [2020, '2025/2026', 600],
    [2026, '2025/2026', 100],
]);

it('finds the entry year of the cohort at a level', function (int $level, string $session, int $entryYear) {
    expect(AcademicLevel::entryYearFor($level, $session))->toBe($entryYear);
})->with([
    [100, '2025/2026', 2025],
    [300, '2025/2026', 2023],
    [600, '2025/2026', 2020],
]);
