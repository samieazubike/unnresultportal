<?php

use App\Enums\ResultStatus;
use App\Models\Course;
use App\Models\Recommendation;
use App\Models\Registration;
use App\Models\Result;
use App\Models\Student;
use App\Services\Llm\LlmDriver;
use App\Services\Llm\LlmUnavailable;
use App\Services\RecommendationService;

/**
 * README §8. The claims under test are the ones the defence rests on:
 * the model sees only computed facts, and the feature keeps working when the
 * model is unreachable.
 */
beforeEach(function () {
    $this->student = Student::factory()->create();
});

/**
 * Give the student a published result worth $units credits at $total marks.
 */
function gradeStudent(Student $student, float $total, int $units, string $session, int $semester, ?string $code = null): void
{
    $course = Course::factory()->create([
        'dept_id' => $student->dept_id,
        'credit_units' => $units,
        'course_code' => $code ?? strtoupper(fake()->unique()->lexify('???')).' '.fake()->unique()->numberBetween(101, 599),
    ]);

    $registration = Registration::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'session' => $session,
        'semester' => $semester,
    ]);

    Result::factory()->scoring($total)->create([
        'registration_id' => $registration->id,
        'status' => ResultStatus::Published,
    ]);
}

describe('facts', function () {
    it('computes the CGPA, classification and semester count from SQL', function () {
        gradeStudent($this->student, 75, 3, '2024/2025', 1);
        gradeStudent($this->student, 55, 3, '2024/2025', 2);

        $facts = app(RecommendationService::class)->facts($this->student);

        expect($facts['cgpa'])->toBe(4.0)
            ->and($facts['classification'])->toBe('Second Class Honours (Upper Division)')
            ->and($facts['semesters_completed'])->toBe(2)
            ->and($facts['credit_units_passed'])->toBe(6);
    });

    it('identifies the weakest courses and the failed ones', function () {
        gradeStudent($this->student, 75, 3, '2024/2025', 1);
        gradeStudent($this->student, 30, 3, '2024/2025', 1, 'MTH 201');
        gradeStudent($this->student, 47, 3, '2024/2025', 1, 'COS 205');

        $facts = app(RecommendationService::class)->facts($this->student);

        expect(array_column($facts['weakest_courses'], 'course_code'))->toBe(['MTH 201', 'COS 205'])
            ->and($facts['failed_courses'])->toBe(['MTH 201']);
    });

    it('reports a negative slope for a declining student', function () {
        gradeStudent($this->student, 85, 3, '2023/2024', 1);
        gradeStudent($this->student, 65, 3, '2023/2024', 2);
        gradeStudent($this->student, 42, 3, '2024/2025', 1);

        expect(app(RecommendationService::class)->facts($this->student)['cgpa_slope'])->toBeLessThan(0);
    });

    it('counts published results only', function () {
        $course = Course::factory()->create(['dept_id' => $this->student->dept_id, 'credit_units' => 3]);
        $registration = Registration::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $course->id,
        ]);
        Result::factory()->scoring(75)->create([
            'registration_id' => $registration->id,
            'status' => ResultStatus::Approved,
        ]);

        expect(app(RecommendationService::class)->facts($this->student)['semesters_completed'])->toBe(0);
    });
});

describe('rules', function () {
    it('flags a declining CGPA', function () {
        gradeStudent($this->student, 85, 3, '2023/2024', 1);
        gradeStudent($this->student, 55, 3, '2023/2024', 2);

        $service = app(RecommendationService::class);

        expect($service->flags($service->facts($this->student)))->toContain('cgpa_declining_2_semesters');
    });

    it('flags a student at risk below 1.5', function () {
        gradeStudent($this->student, 42, 3, '2024/2025', 1);  // E, 1 point

        $service = app(RecommendationService::class);

        expect($service->flags($service->facts($this->student)))->toContain('at_risk_below_1_5');
    });

    it('flags first class standing', function () {
        gradeStudent($this->student, 85, 3, '2024/2025', 1);

        $service = app(RecommendationService::class);

        expect($service->flags($service->facts($this->student)))->toContain('first_class_standing');
    });

    it('names the weak courses in the flag', function () {
        gradeStudent($this->student, 30, 3, '2024/2025', 1, 'MTH 201');

        $service = app(RecommendationService::class);
        $flags = $service->flags($service->facts($this->student));

        expect($flags)->toContain('weak_in:MTH 201')
            ->and($flags)->toContain('failed:MTH 201');
    });

    it('flags a student with nothing published yet', function () {
        $service = app(RecommendationService::class);

        expect($service->flags($service->facts($this->student)))->toContain('no_published_results');
    });
});

describe('the model never sees raw results', function () {
    it('sends only computed facts and flags', function () {
        gradeStudent($this->student, 30, 3, '2024/2025', 1, 'MTH 201');
        gradeStudent($this->student, 68, 3, '2024/2025', 2);

        $captured = null;

        $this->mock(LlmDriver::class, function ($mock) use (&$captured) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('complete')->andReturnUsing(function (string $system, string $user) use (&$captured) {
                $captured = $user;

                return 'Three sentences of advice.';
            });
        });

        app(RecommendationService::class)->forStudent($this->student, '2024/2025');

        // The prompt carries aggregates and course codes, and nothing else:
        // no CA score, no exam score, no total, no result or registration id.
        expect($captured)->toContain('Current CGPA:')
            ->and($captured)->toContain('Flags:')
            ->and($captured)->not->toContain('ca_score')
            ->and($captured)->not->toContain('exam_score')
            ->and($captured)->not->toContain('total_score')
            ->and($captured)->not->toContain('registration_id')
            ->and($captured)->not->toMatch('/\b(30|68)\.00\b/');
    });

    it('instructs the model to invent nothing', function () {
        gradeStudent($this->student, 68, 3, '2024/2025', 1);
        $captured = null;

        $this->mock(LlmDriver::class, function ($mock) use (&$captured) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('complete')->andReturnUsing(function (string $system) use (&$captured) {
                $captured = $system;

                return 'Advice.';
            });
        });

        app(RecommendationService::class)->forStudent($this->student, '2024/2025');

        expect($captured)->toContain('Use ONLY the facts supplied')
            ->and($captured)->toContain('Invent no grade')
            ->and($captured)->toContain('exactly three sentences');
    });
});

describe('offline fallback', function () {
    it('renders rule-based text when the model throws', function () {
        gradeStudent($this->student, 85, 3, '2023/2024', 1);
        gradeStudent($this->student, 45, 3, '2023/2024', 2);

        $this->mock(LlmDriver::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('complete')->andThrow(new LlmUnavailable('Ollama is not running.'));
        });

        $recommendation = app(RecommendationService::class)->forStudent($this->student, '2023/2024');

        expect($recommendation->source)->toBe(Recommendation::SOURCE_FALLBACK)
            ->and($recommendation->body)->not->toBeEmpty()
            ->and($recommendation->body)->toContain('CGPA');
    });

    it('skips the model entirely when none is configured', function () {
        gradeStudent($this->student, 68, 3, '2024/2025', 1);

        $this->mock(LlmDriver::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
            $mock->shouldNotReceive('complete');
        });

        expect(app(RecommendationService::class)->forStudent($this->student, '2024/2025')->source)
            ->toBe(Recommendation::SOURCE_FALLBACK);
    });

    it('quotes only figures that appear in the facts', function () {
        gradeStudent($this->student, 30, 3, '2024/2025', 1, 'MTH 201');

        $service = app(RecommendationService::class);
        $facts = $service->facts($this->student);

        $text = $service->fallbackText($facts, $service->flags($facts));

        expect($text)->toContain(number_format($facts['cgpa'], 2))
            ->and($text)->toContain('MTH 201')
            ->and(substr_count($text, '.'))->toBeGreaterThanOrEqual(3);
    });

    it('says so plainly when there is nothing to assess', function () {
        $recommendation = app(RecommendationService::class)->forStudent($this->student, '2024/2025');

        expect($recommendation->body)->toContain('no published results')
            ->and($recommendation->source)->toBe(Recommendation::SOURCE_FALLBACK);
    });
});

describe('cache', function () {
    it('does not call the model again on a cache hit', function () {
        gradeStudent($this->student, 68, 3, '2024/2025', 1);

        $this->mock(LlmDriver::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('complete')->once()->andReturn('Generated once.');
        });

        $service = app(RecommendationService::class);
        $service->forStudent($this->student, '2024/2025');
        $second = $service->forStudent($this->student, '2024/2025');

        expect($second->body)->toBe('Generated once.')
            ->and(Recommendation::count())->toBe(1);
    });

    it('regenerates on demand', function () {
        gradeStudent($this->student, 68, 3, '2024/2025', 1);

        $this->mock(LlmDriver::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('complete')->twice()->andReturn('First.', 'Second.');
        });

        $service = app(RecommendationService::class);
        $service->forStudent($this->student, '2024/2025');

        expect($service->forStudent($this->student, '2024/2025', refresh: true)->body)->toBe('Second.')
            ->and(Recommendation::count())->toBe(1);
    });

    it('stores the facts and flags alongside the phrasing', function () {
        gradeStudent($this->student, 30, 3, '2024/2025', 1, 'MTH 201');

        $recommendation = app(RecommendationService::class)->forStudent($this->student, '2024/2025');

        expect($recommendation->facts)->toHaveKey('cgpa')
            ->and($recommendation->flags)->toContain('failed:MTH 201');
    });
});
