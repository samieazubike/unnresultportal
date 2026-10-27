<?php

use App\Enums\RegistrationSource;
use App\Enums\ResultStatus;
use App\Models\Course;
use App\Models\Department;
use App\Models\Registration;
use App\Models\Result;
use App\Models\Student;
use App\Models\User;
use App\Services\VisualizationService;
use Database\Seeders\FeaturedStudentHistorySeeder;

beforeEach(function () {
    User::factory()->examOfficer()->create(['email' => 'examofficer@unn.edu.ng']);
    User::factory()->ictDirector()->create(['email' => 'ictdirector@unn.edu.ng']);

    $department = Department::factory()->create(['dept_name' => 'Computer Science']);
    $head = User::factory()->lecturer()->create();

    foreach ([
        'COS101', 'MTH101', 'STA101', 'COS102', 'MTH102', 'COS104',
        'COS201', 'MTH201', 'COS203', 'COS202', 'STA202', 'COS204',
        'COS301', 'COS303', 'COS305',
    ] as $code) {
        Course::factory()->headedBy($head)->create([
            'course_code' => $code,
            'credit_units' => 3,
            'dept_id' => $department->id,
        ]);
    }

    $this->student = Student::factory()->create([
        'user_id' => User::factory()->student()->create(['email' => FeaturedStudentHistorySeeder::EMAIL])->id,
        'dept_id' => $department->id,
        'entry_year' => 2026,
    ]);
});

it('publishes five semesters of history through the workflow', function () {
    $this->seed(FeaturedStudentHistorySeeder::class);

    expect($this->student->fresh()->entry_year)->toBe(2023);

    $results = Result::query()
        ->whereHas('registration', fn ($query) => $query->where('student_id', $this->student->id))
        ->with('signature')
        ->get();

    expect($results)->toHaveCount(16)
        ->and($results->every(fn (Result $result) => $result->status === ResultStatus::Published))->toBeTrue()
        ->and($results->every(fn (Result $result) => $result->signature !== null))->toBeTrue();

    $trend = app(VisualizationService::class)->cgpaTrend($this->student->id);

    expect(array_column($trend, 'label'))->toBe([
        '2023/2024 1st', '2023/2024 2nd', '2024/2025 1st', '2024/2025 2nd', '2025/2026 1st',
    ]);
});

it('registers the failed course again as a student carryover', function () {
    $this->seed(FeaturedStudentHistorySeeder::class);

    $retake = Registration::query()
        ->with('result')
        ->where('student_id', $this->student->id)
        ->whereRelation('course', 'course_code', 'MTH201')
        ->where('session', '2025/2026')
        ->sole();

    expect($retake->source)->toBe(RegistrationSource::Student)
        ->and($retake->result->grade)->not->toBe('F')
        ->and($this->student->fresh()->carryoverCourseIds())->toBe([]);
});

it('is safe to run twice', function () {
    $this->seed(FeaturedStudentHistorySeeder::class);
    $this->seed(FeaturedStudentHistorySeeder::class);

    expect(Registration::query()->where('student_id', $this->student->id)->count())->toBe(16);
});
