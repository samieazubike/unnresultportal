<?php

use App\Enums\RegistrationSource;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Registration;
use App\Models\RegistrationWindow;
use App\Models\Result;
use App\Models\Student;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->department = Department::factory()->create();
    [$this->courseA, $this->courseB, $this->courseC] = Course::factory()
        ->count(3)
        ->sequence(['course_code' => 'CSC 101'], ['course_code' => 'CSC 103'], ['course_code' => 'CSC 105'])
        ->create(['dept_id' => $this->department->id]);

    // 100L in 2025/2026 means an entry year of 2025.
    $this->fresher = Student::factory()->create(['dept_id' => $this->department->id, 'entry_year' => 2025]);
    $this->stalist = Student::factory()->create(['dept_id' => $this->department->id, 'entry_year' => 2024]);
    $this->outsider = Student::factory()->create(['entry_year' => 2025]);
});

function saveOffering(array $courseIds, array $overrides = []): TestResponse
{
    return test()->actingAs(test()->admin)->put(route('admin.registration.offerings'), [
        'dept_id' => test()->department->id,
        'level' => 100,
        'session' => '2025/2026',
        'semester' => 1,
        'course_ids' => $courseIds,
        ...$overrides,
    ]);
}

function registeredCourseIds(Student $student): array
{
    return Registration::query()
        ->where('student_id', $student->id)
        ->where('session', '2025/2026')
        ->where('semester', 1)
        ->orderBy('course_id')
        ->pluck('course_id')
        ->all();
}

it('registers only the matching department and level straight away', function () {
    saveOffering([$this->courseA->id, $this->courseB->id])->assertRedirect()->assertSessionHas('success');

    expect(registeredCourseIds($this->fresher))->toBe([$this->courseA->id, $this->courseB->id])
        ->and(registeredCourseIds($this->stalist))->toBe([])
        ->and(registeredCourseIds($this->outsider))->toBe([])
        ->and(Registration::query()->where('student_id', $this->fresher->id)->first()->source)->toBe(RegistrationSource::Offering)
        ->and(CourseOffering::query()->count())->toBe(2);
});

it('adds only new courses on re-save and does not force back a dropped one', function () {
    saveOffering([$this->courseA->id, $this->courseB->id]);

    Registration::query()->where('student_id', $this->fresher->id)->where('course_id', $this->courseB->id)->delete();

    saveOffering([$this->courseA->id, $this->courseB->id, $this->courseC->id]);

    expect(registeredCourseIds($this->fresher))->toBe([$this->courseA->id, $this->courseC->id]);
});

it('gives the full list to a student added to the roster later', function () {
    saveOffering([$this->courseA->id, $this->courseB->id]);

    $late = Student::factory()->create(['dept_id' => $this->department->id, 'entry_year' => 2025]);

    saveOffering([$this->courseA->id, $this->courseB->id]);

    expect(registeredCourseIds($late))->toBe([$this->courseA->id, $this->courseB->id]);
});

it('removes a course taken off the offering only where it is safe to', function () {
    saveOffering([$this->courseA->id, $this->courseB->id]);

    $withResult = Student::factory()->create(['dept_id' => $this->department->id, 'entry_year' => 2025]);
    saveOffering([$this->courseA->id, $this->courseB->id]);
    Result::factory()->create([
        'registration_id' => Registration::query()
            ->where('student_id', $withResult->id)
            ->where('course_id', $this->courseB->id)
            ->value('id'),
    ]);

    $selfAdded = Student::factory()->create(['dept_id' => $this->department->id, 'entry_year' => 2025]);
    Registration::factory()->forSession('2025/2026', 1)->create([
        'student_id' => $selfAdded->id,
        'course_id' => $this->courseB->id,
    ]);

    saveOffering([$this->courseA->id]);

    expect(registeredCourseIds($this->fresher))->toBe([$this->courseA->id])
        ->and(registeredCourseIds($withResult))->toBe([$this->courseA->id, $this->courseB->id])
        ->and(registeredCourseIds($selfAdded))->toContain($this->courseB->id)
        ->and(CourseOffering::query()->pluck('course_id')->all())->toBe([$this->courseA->id]);
});

it('validates the level range', function () {
    saveOffering([$this->courseA->id], ['level' => 700])->assertSessionHasErrors('level');
    saveOffering([$this->courseA->id], ['level' => 600])->assertSessionHasNoErrors();
});

it('shows the offering screen to an administrator', function () {
    saveOffering([$this->courseA->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.registration', ['dept_id' => $this->department->id, 'level' => 100, 'session' => '2025/2026', 'semester' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/registration')
            ->where('offeredCourseIds', [$this->courseA->id])
            ->where('cohortSize', 1)
            ->where('levels', [100, 200, 300, 400, 500, 600])
            ->has('windows', 2)
        );
});

it('opens and closes the registration window', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.registration.window'), ['session' => '2025/2026', 'semester' => 1, 'is_open' => true])
        ->assertRedirect();

    expect(RegistrationWindow::isOpen('2025/2026', 1))->toBeTrue()
        ->and(RegistrationWindow::query()->first()->updated_by)->toBe($this->admin->id);

    $this->actingAs($this->admin)
        ->put(route('admin.registration.window'), ['session' => '2025/2026', 'semester' => 1, 'is_open' => false]);

    expect(RegistrationWindow::isOpen('2025/2026', 1))->toBeFalse()
        ->and(RegistrationWindow::query()->count())->toBe(1);
});

it('keeps everyone but the administrator out', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('admin.registration'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.registration.window'), ['session' => '2025/2026', 'semester' => 1, 'is_open' => true])->assertForbidden();
    $this->actingAs($user)->put(route('admin.registration.offerings'), [
        'dept_id' => $this->department->id, 'level' => 100, 'session' => '2025/2026', 'semester' => 1, 'course_ids' => [],
    ])->assertForbidden();
})->with(['student', 'lecturer', 'examOfficer', 'ictDirector']);
