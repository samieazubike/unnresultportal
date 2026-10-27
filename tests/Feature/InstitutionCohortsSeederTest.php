<?php

use App\Enums\ResultStatus;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Result;
use App\Models\Student;
use App\Models\User;
use App\Services\VisualizationService;
use Database\Seeders\InstitutionCohortsSeeder;

beforeEach(function () {
    User::factory()->examOfficer()->create(['email' => 'examofficer@unn.edu.ng']);
    User::factory()->ictDirector()->create(['email' => 'ictdirector@unn.edu.ng']);
});

/**
 * One student per department and intake keeps the run quick while still
 * exercising every sheet.
 */
function seedSmallInstitution(): void
{
    $seeder = app(InstitutionCohortsSeeder::class);
    $seeder->cohortSizes = Department::query()->pluck('dept_name')
        ->merge(['Computer Science', 'Mathematics', 'Statistics', 'Physics and Astronomy', 'Pure and Industrial Chemistry', 'Electronic Engineering', 'Mechanical Engineering', 'Microbiology', 'Biochemistry', 'Economics'])
        ->mapWithKeys(fn (string $name) => [$name => 1])
        ->all();
    $seeder->run();
}

it('builds four faculties of ten departments, each with three intakes', function () {
    seedSmallInstitution();

    expect(Faculty::query()->count())->toBe(4)
        ->and(Department::query()->count())->toBe(10)
        ->and(Student::query()->count())->toBe(30)
        ->and(Student::query()->distinct()->pluck('entry_year')->sort()->values()->all())->toBe([2023, 2024, 2025]);
});

it('gives every department and faculty published results to compare', function () {
    seedSmallInstitution();

    $visualization = app(VisualizationService::class);

    expect($visualization->departmentPerformance())->toHaveCount(10)
        ->and($visualization->facultyPerformance())->toHaveCount(4);
});

it('heads every course and leaves the live session mid-workflow', function () {
    seedSmallInstitution();

    expect(Course::query()->whereNull('lecturer_id')->count())->toBe(0);

    $liveStatus = fn (string $code) => Result::query()
        ->whereHas('registration', fn ($query) => $query
            ->where('session', '2025/2026')
            ->whereRelation('course', 'course_code', $code))
        ->pluck('status')
        ->unique()
        ->values()
        ->all();

    expect($liveStatus('MTH101'))->toBe([ResultStatus::Submitted])
        ->and($liveStatus('ELE201'))->toBe([ResultStatus::Approved])
        ->and($liveStatus('STA301'))->toBe([ResultStatus::Draft])
        ->and($liveStatus('COS301'))->toBe([ResultStatus::Published]);
});

it('is safe to run twice', function () {
    seedSmallInstitution();
    $results = Result::query()->count();

    seedSmallInstitution();

    expect(Student::query()->count())->toBe(30)
        ->and(Result::query()->count())->toBe($results);
});
