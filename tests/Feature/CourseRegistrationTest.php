<?php

use App\Enums\RegistrationSource;
use App\Enums\ResultStatus;
use App\Models\Course;
use App\Models\Department;
use App\Models\Registration;
use App\Models\RegistrationWindow;
use App\Models\Result;
use App\Models\Student;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->department = Department::factory()->create();
    $this->student = Student::factory()->create(['dept_id' => $this->department->id, 'entry_year' => 2024]);

    $this->preselected = Course::factory()->create(['dept_id' => $this->department->id, 'course_code' => 'CSC 201']);
    $this->elective = Course::factory()->create(['dept_id' => $this->department->id, 'course_code' => 'CSC 205']);
    $this->failed = Course::factory()->create(['course_code' => 'MTH 101']);
    $this->foreign = Course::factory()->create(['course_code' => 'PHY 101']);

    $this->registration = Registration::factory()->forSession('2025/2026', 1)->fromOffering()->create([
        'student_id' => $this->student->id,
        'course_id' => $this->preselected->id,
    ]);

    // Failed MTH 101 last session: a carryover.
    Result::factory()->withGrade('F')->create([
        'registration_id' => Registration::factory()->forSession('2024/2025', 1)->create([
            'student_id' => $this->student->id,
            'course_id' => $this->failed->id,
        ])->id,
        'status' => ResultStatus::Published,
    ]);
});

function openWindow(): void
{
    RegistrationWindow::factory()->open()->create(['session' => '2025/2026', 'semester' => 1]);
}

function addCourses(array $courseIds): TestResponse
{
    return test()->actingAs(test()->student->user)->post('/student/registration', [
        'course_ids' => $courseIds,
        'session' => '2025/2026',
        'semester' => 1,
    ]);
}

it('shows pre-selected courses, carryovers and the window state', function () {
    openWindow();

    $this->actingAs($this->student->user)
        ->get('/student/registration')
        ->assertInertia(fn (Assert $page) => $page
            ->component('student/registration')
            ->where('session', '2025/2026')
            ->where('semester', 1)
            ->where('isOpen', true)
            ->where('student.level', 200)
            ->has('registered', 1)
            ->where('registered.0.source', RegistrationSource::Offering->value)
            ->has('carryovers', 1)
            ->where('carryovers.0.course_code', 'MTH 101')
            ->has('available', 1)
            ->where('available.0.course_code', 'CSC 205')
        );
});

it('refuses to add or drop while the window is closed', function () {
    addCourses([$this->elective->id])->assertSessionHas('error');

    $this->actingAs($this->student->user)
        ->delete("/student/registration/{$this->registration->id}")
        ->assertSessionHas('error');

    expect(Registration::query()->where('student_id', $this->student->id)->where('session', '2025/2026')->count())->toBe(1);
});

it('lets the student add a carryover and a department course once open', function () {
    openWindow();

    addCourses([$this->failed->id, $this->elective->id])->assertSessionHas('success');

    $added = Registration::query()
        ->where('student_id', $this->student->id)
        ->where('session', '2025/2026')
        ->whereIn('course_id', [$this->failed->id, $this->elective->id])
        ->get();

    expect($added)->toHaveCount(2)
        ->and($added->every(fn (Registration $r) => $r->source === RegistrationSource::Student))->toBeTrue();
});

it('marks a registered carryover as such', function () {
    openWindow();
    addCourses([$this->failed->id]);

    $this->actingAs($this->student->user)
        ->get('/student/registration')
        ->assertInertia(fn (Assert $page) => $page
            ->where('carryovers', [])
            ->where('registered.0.course_code', 'CSC 201')
            ->where('registered.1.course_code', 'MTH 101')
            ->where('registered.1.is_carryover', true)
        );
});

it('refuses a course from another department that is not a carryover', function () {
    openWindow();

    addCourses([$this->foreign->id])->assertSessionHas('error');

    expect(Registration::query()->where('course_id', $this->foreign->id)->exists())->toBeFalse();
});

it('lets the student drop a pre-selected course once open', function () {
    openWindow();

    $this->actingAs($this->student->user)
        ->delete("/student/registration/{$this->registration->id}")
        ->assertSessionHas('success');

    expect($this->registration->fresh())->toBeNull();
});

it('will not drop a course that already has a result', function () {
    openWindow();
    Result::factory()->create(['registration_id' => $this->registration->id]);

    $this->actingAs($this->student->user)
        ->delete("/student/registration/{$this->registration->id}")
        ->assertSessionHas('error');

    expect($this->registration->fresh())->not->toBeNull();
});

it('no longer offers a carryover once it has been passed', function () {
    Result::factory()->withGrade('B')->create([
        'registration_id' => Registration::factory()->forSession('2024/2025', 2)->create([
            'student_id' => $this->student->id,
            'course_id' => $this->failed->id,
        ])->id,
        'status' => ResultStatus::Published,
    ]);

    expect($this->student->carryoverCourseIds())->toBe([]);
});
