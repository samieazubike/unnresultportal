<?php

use App\Enums\ResultStatus;
use App\Models\Course;
use App\Models\Recommendation;
use App\Models\Registration;
use App\Models\Result;
use App\Models\Student;
use App\Models\User;
use App\Services\Llm\LlmDriver;
use App\Services\ResultIntegrityService;
use Database\Factories\CourseFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The definition-of-done items from README §15, asserted through real HTTP
 * requests rather than against the services directly.
 */
beforeEach(function () {
    $this->head = User::factory()->lecturer()->create();
    $this->coLecturer = User::factory()->lecturer()->create();
    $this->officer = User::factory()->examOfficer()->create();
    $this->director = User::factory()->ictDirector()->create();
    $this->admin = User::factory()->admin()->create();

    $this->course = Course::factory()->create(['course_code' => 'COS 421']);
    CourseFactory::assignTeam($this->course, [$this->head, $this->coLecturer], head: $this->head);

    $this->student = Student::factory()->create();
    $this->registration = Registration::factory()->create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'session' => '2025/2026',
        'semester' => 1,
    ]);
});

describe('role-based landing', function () {
    it('sends each role to its own dashboard', function (string $state, string $path) {
        $user = User::factory()->{$state}()->create();

        if ($state === 'student') {
            Student::factory()->create(['user_id' => $user->id]);
        }

        $this->actingAs($user)->get('/dashboard')->assertRedirect($path);
    })->with([
        ['student', '/student/dashboard'],
        ['lecturer', '/lecturer/dashboard'],
        ['examOfficer', '/dashboards/department'],
        ['ictDirector', '/dashboards/department'],
        ['admin', '/admin/dashboard'],
    ]);

    it('keeps a student out of staff screens', function (string $path) {
        $this->actingAs($this->student->user)->get($path)->assertForbidden();
    })->with(['/review', '/publish', '/integrity', '/admin/courses', '/lecturer/courses']);

    it('keeps a lecturer out of the review and publication screens', function (string $path) {
        $this->actingAs($this->head)->get($path)->assertForbidden();
    })->with(['/review', '/publish', '/admin/courses']);
});

describe('score entry', function () {
    it('lets a co-lecturer save scores and computes the grade', function () {
        $this->actingAs($this->coLecturer)
            ->post("/lecturer/courses/{$this->course->id}/scores", [
                'scores' => [[
                    'registration_id' => $this->registration->id,
                    'ca_score' => 24,
                    'exam_score' => 46,
                ]],
            ])
            ->assertRedirect();

        $result = Result::query()->sole();

        expect($result->total_score)->toBe(70.0)
            ->and($result->grade)->toBe('A')
            ->and($result->grade_point)->toBe(5);
    });

    it('refuses a lecturer who does not teach the course', function () {
        $outsider = User::factory()->lecturer()->create();

        $this->actingAs($outsider)
            ->post("/lecturer/courses/{$this->course->id}/scores", [
                'scores' => [[
                    'registration_id' => $this->registration->id,
                    'ca_score' => 24,
                    'exam_score' => 46,
                ]],
            ])
            ->assertForbidden();

        expect(Result::count())->toBe(0);
    });

    it('rejects a CA score above 30', function () {
        $this->actingAs($this->head)
            ->post("/lecturer/courses/{$this->course->id}/scores", [
                'scores' => [[
                    'registration_id' => $this->registration->id,
                    'ca_score' => 31,
                    'exam_score' => 46,
                ]],
            ])
            ->assertSessionHasErrors('scores.0.ca_score');
    });

    it('rejects an exam score above 70', function () {
        $this->actingAs($this->head)
            ->post("/lecturer/courses/{$this->course->id}/scores", [
                'scores' => [[
                    'registration_id' => $this->registration->id,
                    'ca_score' => 24,
                    'exam_score' => 71,
                ]],
            ])
            ->assertSessionHasErrors('scores.0.exam_score');
    });

    it('never accepts a grade from input', function () {
        $this->actingAs($this->head)
            ->post("/lecturer/courses/{$this->course->id}/scores", [
                'scores' => [[
                    'registration_id' => $this->registration->id,
                    'ca_score' => 5,
                    'exam_score' => 10,
                    'grade' => 'A',
                    'grade_point' => 5,
                ]],
            ]);

        expect(Result::query()->sole()->grade)->toBe('F');
    });
});

describe('submission is the head\'s alone', function () {
    beforeEach(function () {
        Result::factory()->scoring(70)->create([
            'registration_id' => $this->registration->id,
            'status' => ResultStatus::Draft,
        ]);
    });

    it('lets the head submit', function () {
        $this->actingAs($this->head)
            ->post("/lecturer/courses/{$this->course->id}/scores/submit", [
                'session' => '2025/2026',
                'semester' => 1,
            ])
            ->assertRedirect();

        expect(Result::query()->sole()->status)->toBe(ResultStatus::Submitted);
    });

    it('refuses a co-lecturer', function () {
        $this->actingAs($this->coLecturer)
            ->post("/lecturer/courses/{$this->course->id}/scores/submit", [
                'session' => '2025/2026',
                'semester' => 1,
            ])
            ->assertForbidden();

        expect(Result::query()->sole()->status)->toBe(ResultStatus::Draft);
    });
});

describe('reviewing a sheet', function () {
    beforeEach(function () {
        $this->result = Result::factory()->scoring(70)->create([
            'registration_id' => $this->registration->id,
            'status' => ResultStatus::Submitted,
        ]);

        $this->sheetUrl = route('review.show', [$this->course->id, '2025-2026', 1]);
    });

    it('links each queued sheet to its scores', function () {
        $this->actingAs($this->officer)
            ->get('/review')
            ->assertInertia(fn ($page) => $page
                ->component('exam-officer/review')
                ->where('sheets.0.url', $this->sheetUrl)
            );
    });

    it('shows the officer every score on the sheet', function () {
        $this->actingAs($this->officer)
            ->get($this->sheetUrl)
            ->assertInertia(fn ($page) => $page
                ->component('exam-officer/sheet')
                ->where('session', '2025/2026')
                ->has('rows', 1)
                ->where('rows.0.reg_number', $this->student->reg_number)
                ->where('rows.0.total_score', 70)
            );
    });

    it('keeps the sheet away from everyone but the examination officer', function (string $actor) {
        $this->actingAs($this->{$actor})->get($this->sheetUrl)->assertForbidden();
    })->with(['head', 'director', 'admin']);

    it('returns to the queue after a decision', function (string $action, array $extra) {
        $this->actingAs($this->officer)
            ->post("/review/{$action}", [
                'course_id' => $this->course->id,
                'session' => '2025/2026',
                'semester' => 1,
                ...$extra,
            ])
            ->assertRedirect(route('review.index'));
    })->with([
        'approve' => ['approve', []],
        'reject' => ['reject', ['note' => 'The CA column looks transposed.']],
    ]);
});

describe('approval does not publish', function () {
    beforeEach(function () {
        $this->result = Result::factory()->scoring(70)->create([
            'registration_id' => $this->registration->id,
            'status' => ResultStatus::Submitted,
        ]);
    });

    it('moves the sheet to approved and no further', function () {
        $this->actingAs($this->officer)->post('/review/approve', [
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
        ])->assertRedirect();

        expect($this->result->fresh()->status)->toBe(ResultStatus::Approved)
            ->and($this->result->fresh()->published_by)->toBeNull();
    });

    /**
     * The claim from §15: "Approved results are provably invisible to students
     * until published." The result must be absent from the Inertia payload,
     * not merely hidden in the interface.
     */
    it('leaves an approved result absent from the student portal payload', function () {
        $this->actingAs($this->officer)->post('/review/approve', [
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
        ]);

        $this->actingAs($this->student->user)
            ->get('/student/results')
            ->assertInertia(fn ($page) => $page
                ->component('student/results')
                ->where('sessions', [])
                ->where('awaitingRelease', [])
            );
    });

    it('tells neither the student portal nor dashboard that results are pending', function () {
        $this->actingAs($this->officer)->post('/review/approve', [
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
        ]);

        $this->actingAs($this->student->user)
            ->get('/student/dashboard')
            ->assertInertia(fn ($page) => $page
                ->component('student/dashboard')
                ->missing('awaitingRelease')
            );
    });

    it('delivers the recommendation to the student dashboard as a deferred prop', function () {
        $this->mock(LlmDriver::class, fn ($mock) => $mock->shouldReceive('isConfigured')->andReturn(false));

        $this->actingAs($this->student->user)
            ->get('/student/dashboard')
            ->assertInertia(fn ($page) => $page
                ->component('student/dashboard')
                ->missing('recommendation')
                ->loadDeferredProps(fn ($reload) => $reload
                    ->where('recommendation.source', Recommendation::SOURCE_FALLBACK)
                    ->whereType('recommendation.body', 'string')
                )
            );
    });

    it('still shows staff which of a student\'s sessions await release', function () {
        $this->actingAs($this->officer)->post('/review/approve', [
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
        ]);

        $this->actingAs($this->officer)
            ->get("/students/{$this->student->id}/results")
            ->assertInertia(fn ($page) => $page
                ->component('student/results')
                ->where('sessions', [])
                ->has('awaitingRelease', 1)
                ->where('awaitingRelease.0.label', '2025/2026 1st semester')
            );
    });

    it('keeps an approved result out of the CGPA trend', function () {
        $this->actingAs($this->officer)->post('/review/approve', [
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
        ]);

        $this->actingAs($this->student->user)
            ->get('/student/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('cgpaTrend', [])
                ->where('standing.cgpa', 0)
            );
    });

    it('refuses approval from anyone but the examination officer', function (string $actor) {
        $this->actingAs($this->{$actor})->post('/review/approve', [
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
        ])->assertForbidden();

        expect($this->result->fresh()->status)->toBe(ResultStatus::Submitted);
    })->with(['director', 'admin', 'head']);

    it('returns a rejected sheet to the lecturer', function () {
        $this->actingAs($this->officer)->post('/review/reject', [
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
            'note' => 'The CA column looks transposed.',
        ])->assertRedirect();

        expect($this->result->fresh()->status)->toBe(ResultStatus::Draft);
    });

    it('will not reject without a note', function () {
        $this->actingAs($this->officer)->post('/review/reject', [
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
        ])->assertSessionHasErrors('note');
    });
});

describe('publication', function () {
    beforeEach(function () {
        $this->result = Result::factory()->scoring(62)->create([
            'registration_id' => $this->registration->id,
            'status' => ResultStatus::Approved,
        ]);

        app(ResultIntegrityService::class)->freeze($this->result, $this->officer);
    });

    it('moves a released sheet from the queue into the publication history', function () {
        $this->actingAs($this->officer)
            ->get('/publish')
            ->assertInertia(fn ($page) => $page
                ->has('sheets', 1)
                ->where('history', [])
            );

        $this->actingAs($this->officer)->post('/publish', [
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
        ]);

        $this->actingAs($this->officer)
            ->get('/publish')
            ->assertInertia(fn ($page) => $page
                ->component('exam-officer/publish')
                ->where('sheets', [])
                ->has('history', 1)
                ->where('history.0.course_code', 'COS 421')
                ->where('history.0.session', '2025/2026')
                ->where('history.0.results', 1)
                ->where('history.0.published_by', $this->officer->name)
                ->whereType('history.0.published_at', 'string')
            );
    });

    it('releases the result and records who did it', function () {
        $this->actingAs($this->director)->post('/publish', [
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
        ])->assertRedirect();

        expect($this->result->fresh()->status)->toBe(ResultStatus::Published)
            ->and($this->result->fresh()->published_by)->toBe($this->director->id);
    });

    it('makes the result visible to its student, and only to them', function () {
        $this->actingAs($this->director)->post('/publish', [
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
        ]);

        $this->actingAs($this->student->user)
            ->get('/student/results')
            ->assertInertia(fn ($page) => $page
                ->has('sessions', 1)
                ->where('sessions.0.courses.0.course_code', 'COS 421')
                ->where('sessions.0.courses.0.grade', 'B')
            );
    });

    /**
     * The ICT director holds the widest authority in the system. This asserts
     * they cannot use it to change a mark: score fields posted to the publish
     * endpoint are ignored entirely.
     */
    it('cannot alter a score even when score fields are posted', function () {
        $before = $this->result->only(['ca_score', 'exam_score', 'total_score', 'grade', 'grade_point']);

        $this->actingAs($this->director)->post('/publish', [
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
            'ca_score' => 30,
            'exam_score' => 70,
            'total_score' => 100,
            'grade' => 'A',
            'grade_point' => 5,
        ]);

        expect($this->result->fresh()->only(['ca_score', 'exam_score', 'total_score', 'grade', 'grade_point']))
            ->toBe($before);
    });

    it('holds back a result that was tampered with in the database', function () {
        DB::table('results')->where('id', $this->result->id)->update([
            'grade' => 'A',
            'grade_point' => 5,
        ]);

        $this->actingAs($this->director)->post('/publish', [
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
        ])->assertSessionHas('error');

        expect($this->result->fresh()->status)->toBe(ResultStatus::Approved);
    });

    it('lists the tampered result on the integrity screen', function () {
        DB::table('results')->where('id', $this->result->id)->update(['total_score' => 99]);

        $this->actingAs($this->officer)
            ->get('/integrity')
            ->assertInertia(fn ($page) => $page
                ->component('exam-officer/integrity')
                ->has('results', 1)
                ->where('results.0.course_code', 'COS 421')
            );
    });

    it('reports a clean bill when nothing has been altered', function () {
        $this->actingAs($this->officer)
            ->get('/integrity')
            ->assertInertia(fn ($page) => $page->where('results', []));
    });

    it('refuses publication from an administrator', function () {
        $this->actingAs($this->admin)->post('/publish', [
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
        ])->assertForbidden();
    });
});

describe('a student cannot read another student', function () {
    beforeEach(function () {
        Result::factory()->scoring(70)->create([
            'registration_id' => $this->registration->id,
            'status' => ResultStatus::Published,
        ]);

        $this->other = Student::factory()->create();
    });

    it('refuses another student\'s results by URL', function () {
        $this->actingAs($this->other->user)
            ->get("/students/{$this->student->id}/results")
            ->assertForbidden();
    });

    it('refuses another student\'s transcript by URL', function () {
        $this->actingAs($this->other->user)
            ->get("/students/{$this->student->id}/transcript")
            ->assertForbidden();
    });

    it('lets staff read any student', function () {
        $this->actingAs($this->officer)
            ->get("/students/{$this->student->id}/results")
            ->assertOk();
    });
});

describe('transcript', function () {
    beforeEach(function () {
        Result::factory()->scoring(70)->create([
            'registration_id' => $this->registration->id,
            'status' => ResultStatus::Published,
        ]);
    });

    it('downloads as a PDF', function () {
        $response = $this->actingAs($this->student->user)->get('/transcript');

        $response->assertOk()->assertHeader('content-type', 'application/pdf');

        expect($response->getContent())->toStartWith('%PDF');
    });

    it('is named after the registration number', function () {
        $this->actingAs($this->student->user)
            ->get('/transcript')
            ->assertDownload('transcript-'.str_replace('/', '-', $this->student->reg_number).'.pdf');
    });
});

describe('semester result', function () {
    beforeEach(function () {
        Result::factory()->scoring(70)->create([
            'registration_id' => $this->registration->id,
            'status' => ResultStatus::Published,
        ]);
    });

    it('shows a student one semester with every course and score', function () {
        $this->actingAs($this->student->user)
            ->get('/student/results/2025-2026/1')
            ->assertInertia(fn ($page) => $page
                ->component('student/semester-result')
                ->where('semester.session', '2025/2026')
                ->where('semester.semester', 1)
                ->has('semester.courses', 1)
                ->where('semester.courses.0.course_code', 'COS 421')
                ->where('semester.courses.0.total_score', 70)
                ->where('isOwnResults', true)
            );
    });

    it('downloads one semester as a PDF named after the student and semester', function () {
        $response = $this->actingAs($this->student->user)->get('/student/results/2025-2026/1/download');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('result-'.str_replace('/', '-', $this->student->reg_number).'-2025-2026-semester-1.pdf');

        expect($response->getContent())->toStartWith('%PDF');
    });

    it('finds nothing for a semester with no published results', function (string $path) {
        $this->actingAs($this->student->user)->get($path)->assertNotFound();
    })->with(['/student/results/2025-2026/2', '/student/results/2025-2026/2/download']);

    it('does not reveal an approved but unpublished semester', function () {
        $this->registration->result->update(['status' => ResultStatus::Approved]);

        $this->actingAs($this->student->user)->get('/student/results/2025-2026/1')->assertNotFound();
    });

    it('lets staff open and download a student\'s semester', function () {
        $this->actingAs($this->officer)
            ->get("/students/{$this->student->id}/results/2025-2026/1")
            ->assertInertia(fn ($page) => $page
                ->component('student/semester-result')
                ->where('isOwnResults', false)
            );

        $this->actingAs($this->officer)
            ->get("/students/{$this->student->id}/results/2025-2026/1/download")
            ->assertOk();
    });

    it('refuses another student\'s semester by URL', function (string $suffix) {
        $other = Student::factory()->create();

        $this->actingAs($other->user)
            ->get("/students/{$this->student->id}/results/2025-2026/1{$suffix}")
            ->assertForbidden();
    })->with(['', '/download']);
});

describe('dashboards render', function () {
    beforeEach(function () {
        Result::factory()->scoring(70)->create([
            'registration_id' => $this->registration->id,
            'status' => ResultStatus::Published,
        ]);
    });

    it('gives the staff dashboard every chart dataset', function () {
        $this->actingAs($this->officer)
            ->get('/dashboards/department')
            ->assertInertia(fn ($page) => $page
                ->component('staff/dashboard')
                ->has('summary')
                ->has('departmentPerformance')
                ->has('facultyPerformance')
                ->has('failureRates')
                ->has('gradeDistribution', 6)
            );
    });

    it('gives the lecturer their own grade distribution', function () {
        $this->actingAs($this->head)
            ->get('/lecturer/dashboard')
            ->assertInertia(fn ($page) => $page
                ->component('lecturer/dashboard')
                ->where('summary.courses_led', 1)
                ->has('gradeDistribution', 6)
            );
    });

    it('gives the student their trend and standing', function () {
        $this->actingAs($this->student->user)
            ->get('/student/dashboard')
            ->assertInertia(fn ($page) => $page
                ->component('student/dashboard')
                ->has('cgpaTrend', 1)
                ->where('standing.cgpa', 5)
                ->where('standing.classification', 'First Class Honours')
            );
    });
});

it('exposes no API routes at all', function () {
    // §2 and §16: no /api group, no JSON API for the frontend to consume.
    $apiRoutes = collect(app('router')->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'));

    expect($apiRoutes)->toBeEmpty();
});

describe('Excel upload over HTTP', function () {
    it('surfaces every bad row, stages the good one and saves nothing', function () {
        $students = collect(['2021/000101', '2021/000102', '2021/000103'])
            ->map(fn (string $reg) => Student::factory()->create(['reg_number' => $reg]));

        foreach ($students as $student) {
            Registration::factory()->create([
                'student_id' => $student->id,
                'course_id' => $this->course->id,
                'session' => '2025/2026',
                'semester' => 1,
            ]);
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['reg_number', 'ca_score', 'exam_score'],
            ['2021/000101', 24, 46],   // valid
            ['2021/999999', 20, 40],   // unknown student
            ['2021/000102', 45, 46],   // CA above 30
            ['2021/000103', 24, 90],   // exam above 70
            ['2021/000101', 10, 10],   // duplicate
        ], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'upload').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $this->actingAs($this->head)
            ->post("/lecturer/courses/{$this->course->id}/scores/upload", [
                'sheet' => new UploadedFile($path, 'scores.xlsx', null, null, true),
                'session' => '2025/2026',
                'semester' => 1,
            ])
            ->assertSessionHas('error')
            ->assertSessionHas('importErrors', fn (array $errors) => count($errors) === 4)
            // The one readable row still reaches the lecturer's inputs; the
            // four others are typed in by hand.
            ->assertSessionHas('importRows', fn (array $staged) => count($staged) === 1
                && $staged[0]['reg_number'] === '2021/000101');

        // The upload writes nothing at all — not even the readable row.
        expect(Result::count())->toBe(0);
    });

    it('stages a clean sheet into the inputs and saves only when the lecturer does', function () {
        $student = Student::factory()->create(['reg_number' => '2021/000201']);
        $registration = Registration::factory()->create([
            'student_id' => $student->id,
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
        ]);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['reg_number', 'ca_score', 'exam_score'],
            ['2021/000201', 24, 46],
        ], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'upload').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $this->actingAs($this->head)
            ->post("/lecturer/courses/{$this->course->id}/scores/upload", [
                'sheet' => new UploadedFile($path, 'scores.xlsx', null, null, true),
                'session' => '2025/2026',
                'semester' => 1,
            ])
            ->assertSessionHas('success')
            ->assertSessionHas('importRows', fn (array $rows) => count($rows) === 1
                && $rows[0]['registration_id'] === $registration->id
                && $rows[0]['reg_number'] === '2021/000201');

        $staged = session('importRows');

        expect($staged[0]['ca_score'])->toEqual(24)
            ->and($staged[0]['exam_score'])->toEqual(46)
            // The upload parses; it does not write.
            ->and(Result::count())->toBe(0);

        // The staged rows go back through the one writer, which is where the
        // grade is computed.
        $this->actingAs($this->head)
            ->post("/lecturer/courses/{$this->course->id}/scores", [
                'scores' => [[
                    'registration_id' => $staged[0]['registration_id'],
                    'ca_score' => $staged[0]['ca_score'],
                    'exam_score' => $staged[0]['exam_score'],
                ]],
            ])
            ->assertSessionHas('success');

        expect(Result::query()->where('grade', 'A')->count())->toBe(1);
    });

    it('leaves an approved row out of the staged rows', function () {
        Result::factory()->approved()->create(['registration_id' => $this->registration->id]);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['reg_number', 'ca_score', 'exam_score'],
            [$this->student->reg_number, 24, 46],
        ], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'upload').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $this->actingAs($this->head)
            ->post("/lecturer/courses/{$this->course->id}/scores/upload", [
                'sheet' => new UploadedFile($path, 'scores.xlsx', null, null, true),
                'session' => '2025/2026',
                'semester' => 1,
            ])
            ->assertSessionHas('importErrors', fn (array $errors) => count($errors) === 1
                && str_contains($errors[0]['message'], 'locked'))
            ->assertSessionHas('importRows', fn (array $rows) => $rows === []);
    });

    it('downloads the pre-filled template', function () {
        $this->actingAs($this->coLecturer)
            ->get("/lecturer/courses/{$this->course->id}/scores/template?session=2025%2F2026&semester=1")
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    });
});
