<?php

use App\Enums\ResultStatus;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Registration;
use App\Models\Result;
use App\Models\Setting;
use App\Models\Student;
use App\Services\VisualizationService;

/**
 * The five capabilities of README §7.
 *
 * The recurring assertion across this file is that unpublished results are
 * invisible to every aggregate — §9 is explicit that GPA, CGPA and every
 * chart count published results only.
 */
beforeEach(function () {
    $this->viz = app(VisualizationService::class);

    $faculty = Faculty::factory()->create(['faculty_name' => 'Faculty of Physical Sciences']);
    $this->department = Department::factory()->create([
        'dept_name' => 'Computer Science',
        'faculty_id' => $faculty->id,
    ]);
    $this->student = Student::factory()->create(['dept_id' => $this->department->id]);
});

/**
 * Publish a result for the student on a fresh course worth $units credits.
 */
function publishResult(
    Student $student,
    float $total,
    int $units,
    string $session,
    int $semester,
    ResultStatus $status = ResultStatus::Published,
    ?Course $course = null,
): Result {
    $course ??= Course::factory()->create([
        'dept_id' => $student->dept_id,
        'credit_units' => $units,
    ]);

    $registration = Registration::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'session' => $session,
        'semester' => $semester,
    ]);

    return Result::factory()->scoring($total)->create([
        'registration_id' => $registration->id,
        'status' => $status,
    ]);
}

describe('CGPA trend', function () {
    it('computes the semester GPA as a credit-weighted mean', function () {
        // A (5) over 3 units and C (3) over 2 units => (15 + 6) / 5 = 4.20
        publishResult($this->student, 75, 3, '2024/2025', 1);
        publishResult($this->student, 55, 2, '2024/2025', 1);

        expect($this->viz->cgpaTrend($this->student->id))->toBe([[
            'session' => '2024/2025',
            'semester' => 1,
            'gpa' => 4.20,
            'cgpa' => 4.20,
            'label' => '2024/2025 1st',
        ]]);
    });

    it('accumulates the CGPA across semesters with the window function', function () {
        publishResult($this->student, 75, 3, '2024/2025', 1);  // A, 5 x 3 = 15
        publishResult($this->student, 35, 3, '2024/2025', 2);  // F, 0 x 3 = 0

        $trend = $this->viz->cgpaTrend($this->student->id);

        expect($trend)->toHaveCount(2)
            ->and($trend[0]['gpa'])->toBe(5.0)
            ->and($trend[0]['cgpa'])->toBe(5.0)
            ->and($trend[1]['gpa'])->toBe(0.0)
            // Running total: (15 + 0) / (3 + 3) = 2.50
            ->and($trend[1]['cgpa'])->toBe(2.50);
    });

    it('orders points chronologically by session then semester', function () {
        publishResult($this->student, 70, 2, '2025/2026', 1);
        publishResult($this->student, 70, 2, '2024/2025', 2);
        publishResult($this->student, 70, 2, '2024/2025', 1);

        expect(array_column($this->viz->cgpaTrend($this->student->id), 'label'))
            ->toBe(['2024/2025 1st', '2024/2025 2nd', '2025/2026 1st']);
    });

    it('counts published results only', function (ResultStatus $status) {
        publishResult($this->student, 75, 3, '2024/2025', 1, $status);

        expect($this->viz->cgpaTrend($this->student->id))->toBe([]);
    })->with([ResultStatus::Draft, ResultStatus::Submitted, ResultStatus::Approved]);

    it('returns an empty trend for a student with no published results', function () {
        expect($this->viz->cgpaTrend($this->student->id))->toBe([])
            ->and($this->viz->currentCgpa($this->student->id))->toBe(0.0);
    });

    it('reports the current CGPA as the last point of the trend', function () {
        publishResult($this->student, 75, 3, '2024/2025', 1);
        publishResult($this->student, 55, 3, '2024/2025', 2);

        expect($this->viz->currentCgpa($this->student->id))->toBe(4.0);
    });
});

describe('grade distribution', function () {
    it('counts each grade for a course in a session', function () {
        $course = Course::factory()->create(['dept_id' => $this->department->id]);

        foreach ([75, 72, 65, 30] as $score) {
            publishResult(Student::factory()->create(['dept_id' => $this->department->id]), $score, 3, '2024/2025', 1, course: $course);
        }

        $counts = array_column($this->viz->gradeDistribution($course->id, '2024/2025'), 'count', 'grade');

        expect($counts)->toBe(['A' => 2, 'B' => 1, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 1]);
    });

    it('returns a stable A to F axis even when grades are missing', function () {
        $course = Course::factory()->create(['dept_id' => $this->department->id]);

        expect(array_column($this->viz->gradeDistribution($course->id), 'grade'))
            ->toBe(['A', 'B', 'C', 'D', 'E', 'F']);
    });

    it('excludes unpublished results', function () {
        $course = Course::factory()->create(['dept_id' => $this->department->id]);
        publishResult($this->student, 75, 3, '2024/2025', 1, ResultStatus::Approved, $course);

        expect(array_sum(array_column($this->viz->gradeDistribution($course->id), 'count')))->toBe(0);
    });
});

describe('failure-rate flagging', function () {
    it('computes the failure rate per course and session', function () {
        $course = Course::factory()->create(['dept_id' => $this->department->id, 'course_code' => 'MTH 201']);

        // 3 of 4 fail => 75%
        foreach ([30, 32, 38, 70] as $score) {
            publishResult(Student::factory()->create(['dept_id' => $this->department->id]), $score, 3, '2024/2025', 1, course: $course);
        }

        $row = collect($this->viz->courseFailureRates())->firstWhere('course_code', 'MTH 201');

        expect($row['enrolled'])->toBe(4)
            ->and($row['failed'])->toBe(3)
            ->and($row['failure_rate'])->toBe(75.0)
            ->and($row['flagged'])->toBeTrue();
    });

    it('leaves a course below the threshold unflagged', function () {
        $course = Course::factory()->create(['dept_id' => $this->department->id, 'course_code' => 'COS 101']);

        // 1 of 4 fails => 25%, under the default 40
        foreach ([30, 70, 65, 55] as $score) {
            publishResult(Student::factory()->create(['dept_id' => $this->department->id]), $score, 3, '2024/2025', 1, course: $course);
        }

        expect(collect($this->viz->courseFailureRates())->firstWhere('course_code', 'COS 101')['flagged'])->toBeFalse();
    });

    it('honours a threshold the administrator has changed', function () {
        $course = Course::factory()->create(['dept_id' => $this->department->id, 'course_code' => 'STA 101']);

        foreach ([30, 70, 65, 55] as $score) {  // 25%
            publishResult(Student::factory()->create(['dept_id' => $this->department->id]), $score, 3, '2024/2025', 1, course: $course);
        }

        Setting::put(Setting::FAILURE_THRESHOLD, 20);

        expect(collect($this->viz->courseFailureRates())->firstWhere('course_code', 'STA 101')['flagged'])->toBeTrue();
    });

    it('sorts the worst offenders first', function () {
        foreach ([['AAA 101', [30, 30, 30, 70]], ['BBB 101', [30, 70, 70, 70]]] as [$code, $scores]) {
            $course = Course::factory()->create(['dept_id' => $this->department->id, 'course_code' => $code]);

            foreach ($scores as $score) {
                publishResult(Student::factory()->create(['dept_id' => $this->department->id]), $score, 3, '2024/2025', 1, course: $course);
            }
        }

        expect(array_column($this->viz->courseFailureRates(), 'course_code'))->toBe(['AAA 101', 'BBB 101']);
    });
});

describe('departmental and faculty performance', function () {
    it('averages student CGPAs rather than raw grade points', function () {
        // One student with a single A, another with a single F.
        publishResult($this->student, 75, 3, '2024/2025', 1);
        publishResult(Student::factory()->create(['dept_id' => $this->department->id]), 30, 3, '2024/2025', 1);

        $row = collect($this->viz->departmentPerformance())->firstWhere('department', 'Computer Science');

        expect($row['students'])->toBe(2)
            ->and($row['average_cgpa'])->toBe(2.50)   // mean of 5.00 and 0.00
            ->and($row['passes'])->toBe(1)
            ->and($row['failures'])->toBe(1);
    });

    it('rolls the same figures up to faculty level', function () {
        publishResult($this->student, 75, 3, '2024/2025', 1);

        $row = collect($this->viz->facultyPerformance())->firstWhere('faculty', 'Faculty of Physical Sciences');

        expect($row['students'])->toBe(1)
            ->and($row['departments'])->toBe(1)
            ->and($row['average_cgpa'])->toBe(5.0);
    });

    it('ignores unpublished results entirely', function () {
        publishResult($this->student, 75, 3, '2024/2025', 1, ResultStatus::Approved);

        expect($this->viz->departmentPerformance())->toBe([])
            ->and($this->viz->facultyPerformance())->toBe([]);
    });
});

describe('institution summary', function () {
    it('separates results awaiting review from those awaiting release', function () {
        publishResult($this->student, 75, 3, '2024/2025', 1, ResultStatus::Submitted);
        publishResult($this->student, 75, 3, '2024/2025', 2, ResultStatus::Approved);
        publishResult($this->student, 75, 3, '2023/2024', 1, ResultStatus::Published);

        $summary = $this->viz->institutionSummary();

        expect($summary['pending_review'])->toBe(1)
            ->and($summary['awaiting_release'])->toBe(1)
            ->and($summary['published_results'])->toBe(1)
            ->and($summary['students'])->toBe(1);
    });
});
