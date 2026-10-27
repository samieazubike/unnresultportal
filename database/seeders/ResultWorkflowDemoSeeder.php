<?php

namespace Database\Seeders;

use App\Enums\ResultStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Department;
use App\Models\Recommendation;
use App\Models\Registration;
use App\Models\Result;
use App\Models\Student;
use App\Models\User;
use App\Services\CourseOfferingService;
use App\Services\GradingService;
use App\Services\ResultWorkflowService;
use App\Support\AcademicLevel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A full academic history for the fifty Database Management test students,
 * driven through the real workflow so every screen in the report has
 * genuine data behind it.
 *
 * Every sheet travels the way a real one would — scores recorded through
 * GradingService, then submit, approve and publish through
 * ResultWorkflowService, each step performed as the right user and dated
 * when it would have happened. Grades, integrity signatures, the audit
 * trail and published_by are therefore all produced by the application
 * itself, never written here.
 *
 * What it leaves behind:
 *
 *  - 2022/2023 to 2024/2025: six published semesters (100L to 300L), so the
 *    student portal and CGPA trend have history to show.
 *  - 2025/2026 first semester (400L): COS413 published, COS411 approved and
 *    awaiting release, COS410 Database Management submitted and awaiting
 *    the examination officer's review, and COS415 registered but not yet
 *    scored, ready for a spreadsheet upload.
 *  - MTH201 and COS306 run well above the 40% failure threshold, so the
 *    departmental dashboard has courses to flag.
 *  - dbstudent01 improves semester on semester; dbstudent02 declines, so
 *    the recommendation panel has an at-risk case.
 *
 * Runs DatabaseManagementStudentsSeeder first if the cohort is missing.
 * Sheets that already have results are skipped, so it is safe to re-run.
 *
 *   php artisan db:seed --class=ResultWorkflowDemoSeeder
 */
class ResultWorkflowDemoSeeder extends Seeder
{
    private const LEVEL_SESSIONS = [
        100 => '2022/2023',
        200 => '2023/2024',
        300 => '2024/2025',
        400 => '2025/2026',
    ];

    /**
     * Code => [title, credit units, owning department, level, semester,
     * difficulty]. Difficulty shifts the class mean; the two strongly
     * negative ones are the courses the dashboard should flag.
     *
     * @var array<string, array{0: string, 1: int, 2: string, 3: int, 4: int, 5: int}>
     */
    private const COURSES = [
        'COS101' => ['Introduction to Computer Science', 3, 'Computer Science', 100, 1, 4],
        'MTH101' => ['Elementary Mathematics I', 3, 'Mathematics', 100, 1, -4],
        'STA101' => ['Introduction to Statistics', 2, 'Statistics', 100, 1, 0],
        'COS102' => ['Introduction to Problem Solving', 3, 'Computer Science', 100, 2, 2],
        'MTH102' => ['Elementary Mathematics II', 3, 'Mathematics', 100, 2, -6],
        'COS104' => ['Computer Applications', 2, 'Computer Science', 100, 2, 8],
        'COS201' => ['Computer Programming I', 3, 'Computer Science', 200, 1, 0],
        'MTH201' => ['Mathematical Methods I', 3, 'Mathematics', 200, 1, -23],
        'COS203' => ['Discrete Structures', 3, 'Computer Science', 200, 1, -3],
        'COS202' => ['Computer Programming II', 3, 'Computer Science', 200, 2, -2],
        'STA202' => ['Statistics for Physical Sciences', 2, 'Statistics', 200, 2, 1],
        'COS204' => ['Computer Architecture', 3, 'Computer Science', 200, 2, 0],
        'COS301' => ['Operating Systems I', 3, 'Computer Science', 300, 1, -1],
        'COS303' => ['Data Structures and Algorithms', 3, 'Computer Science', 300, 1, -4],
        'COS305' => ['Systems Analysis and Design', 2, 'Computer Science', 300, 1, 5],
        'COS302' => ['Software Engineering', 3, 'Computer Science', 300, 2, 3],
        'COS304' => ['Computer Networks', 3, 'Computer Science', 300, 2, 0],
        'COS306' => ['Compiler Construction', 3, 'Computer Science', 300, 2, -21],
        'COS410' => ['Database Management', 3, 'Computer Science', 400, 1, 2],
        'COS411' => ['Artificial Intelligence', 3, 'Computer Science', 400, 1, -2],
        'COS413' => ['Computer Graphics', 2, 'Computer Science', 400, 1, 3],
        'COS415' => ['Information Security', 3, 'Computer Science', 400, 1, 0],
    ];

    /**
     * How far each current-session sheet gets through the workflow. Draft
     * here means registered but with no score entered yet.
     *
     * @var array<string, ResultStatus>
     */
    private const LIVE_SHEETS = [
        'COS415' => ResultStatus::Draft,
        'COS413' => ResultStatus::Published,
        'COS411' => ResultStatus::Approved,
        'COS410' => ResultStatus::Submitted,
    ];

    /** Courses headed by the named demo lecturer, so their screens are full. */
    private const OKAFOR_COURSES = ['COS101', 'COS201', 'COS301', 'COS410', 'COS411', 'COS415'];

    public function __construct(
        private readonly CourseOfferingService $offerings,
        private readonly GradingService $grading,
        private readonly ResultWorkflowService $workflow,
    ) {}

    public function run(): void
    {
        mt_srand(2026);

        if (! User::query()->where('email', 'dbstudent01@unn.edu.ng')->exists()) {
            $this->call(DatabaseManagementStudentsSeeder::class);
        }

        $officer = User::query()->where('email', 'examofficer@unn.edu.ng')->sole();
        $director = User::query()->where('email', 'ictdirector@unn.edu.ng')->sole();
        $computerScience = Department::query()->where('dept_name', 'Computer Science')->sole();

        $courses = $this->seedCourses();

        $students = Student::query()
            ->whereHas('user', fn ($query) => $query->where('email', 'like', 'dbstudent%@unn.edu.ng'))
            ->with('user')
            ->orderBy('reg_number')
            ->get();

        $abilities = $students->mapWithKeys(fn (Student $student) => [
            $student->id => $this->clamp($this->normal(58, 10), 38, 82),
        ]);

        $slot = 0;

        foreach (self::LEVEL_SESSIONS as $level => $session) {
            foreach ([1, 2] as $semester) {
                $sheetCourses = collect(self::COURSES)
                    ->filter(fn (array $course) => $course[3] === $level && $course[4] === $semester)
                    ->keys();

                if ($sheetCourses->isEmpty()) {
                    continue;
                }

                $this->offerings->sync(
                    $computerScience->id,
                    $level,
                    $session,
                    $semester,
                    $sheetCourses->map(fn (string $code) => $courses[$code]->id)->all(),
                );

                $publishedOn = $this->publicationDate($session, $semester);

                foreach ($sheetCourses->values() as $index => $code) {
                    $target = self::LIVE_SHEETS[$code] ?? ResultStatus::Published;

                    $this->runSheet(
                        $courses[$code],
                        $session,
                        $semester,
                        $target,
                        fn (Student $student) => $this->score($student, $abilities[$student->id], $slot, self::COURSES[$code][5]),
                        $publishedOn->copy()->addDays($index * 2),
                        $officer,
                        $index % 2 === 0 ? $officer : $director,
                    );
                }

                $slot++;
            }
        }

        Carbon::setTestNow();
        Auth::forgetUser();

        // Advice cached before this history existed would describe an empty
        // record; drop it so the dashboard regenerates it on next view.
        Recommendation::query()->whereIn('student_id', $students->modelKeys())->delete();

        $this->command?->info(sprintf(
            'Results: %d published, %d approved awaiting release, %d submitted awaiting review.',
            Result::query()->where('status', ResultStatus::Published)->count(),
            Result::query()->where('status', ResultStatus::Approved)->count(),
            Result::query()->where('status', ResultStatus::Submitted)->count(),
        ));
    }

    /**
     * Create any missing course and give it a teaching team with a head.
     *
     * @return array<string, Course>
     */
    private function seedCourses(): array
    {
        $okafor = User::query()->where('email', 'lecturer@unn.edu.ng')->sole();
        $lecturers = User::query()->where('role', UserRole::Lecturer)->whereKeyNot($okafor->id)->get();
        $departments = Department::query()->pluck('id', 'dept_name');

        $courses = [];

        foreach (self::COURSES as $code => [$title, $units, $deptName]) {
            $course = Course::query()->firstOrCreate(['course_code' => $code], [
                'course_title' => $title,
                'credit_units' => $units,
                'dept_id' => $departments[$deptName] ?? $departments['Computer Science'],
            ]);

            if ($course->lecturer_id === null) {
                $head = in_array($code, self::OKAFOR_COURSES, strict: true) ? $okafor : $lecturers->random();
                $team = $lecturers->whereNotIn('id', [$head->id])->random(mt_rand(0, 1))->prepend($head);

                // The head must be on the team; the composite foreign key
                // checks that at commit, so both writes share a transaction.
                DB::transaction(function () use ($course, $team, $head) {
                    $course->lecturers()->sync($team->modelKeys());
                    $course->forceFill(['lecturer_id' => $head->id])->save();
                });
            }

            $courses[$code] = $course->fresh();
        }

        return $courses;
    }

    /**
     * Take one course's sheet as far as $target, as the people who would
     * really have done each step, on the days they would have done it.
     *
     * @param  callable(Student): float  $scoreFor
     */
    private function runSheet(
        Course $course,
        string $session,
        int $semester,
        ResultStatus $target,
        callable $scoreFor,
        Carbon $publishedOn,
        User $officer,
        User $publisher,
    ): void {
        $registrations = Registration::query()
            ->with(['student', 'result'])
            ->where('course_id', $course->id)
            ->where('session', $session)
            ->where('semester', $semester)
            ->get();

        if ($registrations->isEmpty() || $registrations->contains(fn (Registration $registration) => $registration->result !== null)) {
            return;
        }

        if ($target === ResultStatus::Draft) {
            return;
        }

        $head = $course->head;

        $this->actAs($head, $publishedOn->copy()->subDays(24));

        foreach ($registrations as $registration) {
            [$ca, $exam] = $this->split($scoreFor($registration->student));

            $this->grading->record($registration, $ca, $exam, $head);
        }

        $results = Result::query()
            ->with('signature')
            ->whereIn('registration_id', $registrations->modelKeys())
            ->get();

        $this->actAs($head, $publishedOn->copy()->subDays(18));
        $this->workflow->submit($results, $head);

        if ($target === ResultStatus::Submitted) {
            return;
        }

        $this->actAs($officer, $publishedOn->copy()->subDays(9));
        $this->workflow->approve($this->fresh($results), $officer);

        if ($target === ResultStatus::Approved) {
            return;
        }

        $this->actAs($publisher, $publishedOn);
        $this->workflow->publish($this->fresh($results), $publisher);
    }

    /**
     * A student's total for one course: their ability, the course's
     * difficulty, and the noise of any one exam sitting.
     *
     * The two featured students follow a scripted trajectory instead, with
     * little noise and no course difficulty, so their trend reads clearly.
     */
    private function score(Student $student, float $ability, int $slot, int $difficulty): float
    {
        $scripted = match ($student->user?->email) {
            'dbstudent01@unn.edu.ng' => 54 + $slot * 4,
            'dbstudent02@unn.edu.ng' => 76 - $slot * 7,
            default => null,
        };

        $total = $scripted === null
            ? $ability + $difficulty + $this->normal(0, 8)
            : $scripted + $this->normal(0, 3);

        return round($this->clamp($total, 12, 96));
    }

    /**
     * Split a total into whole-mark CA (out of 30) and exam (out of 70).
     *
     * @return array{0: float, 1: float}
     */
    private function split(float $total): array
    {
        $ca = min(30.0, round($total * mt_rand(26, 32) / 100));
        $exam = $total - $ca;

        if ($exam > 70) {
            [$ca, $exam] = [$total - 70, 70.0];
        }

        return [$ca, $exam];
    }

    /**
     * First-semester sheets are released in March of the session's second
     * year, second-semester ones in September.
     */
    private function publicationDate(string $session, int $semester): Carbon
    {
        $year = AcademicLevel::sessionStartYear($session) + 1;

        return $semester === 1
            ? Carbon::create($year, 3, 10, 10, 30)
            : Carbon::create($year, 9, 8, 11, 0);
    }

    private function actAs(User $user, Carbon $at): void
    {
        Carbon::setTestNow($at);
        Auth::setUser($user);
    }

    /**
     * @param  Collection<int, Result>  $results
     * @return Collection<int, Result>
     */
    private function fresh(Collection $results): Collection
    {
        return Result::query()->with('signature')->whereKey($results->modelKeys())->get();
    }

    /**
     * A normally distributed sample (Box–Muller), seeded through mt_rand so
     * every run produces the same dataset.
     */
    private function normal(float $mean, float $deviation): float
    {
        $u = max(mt_rand() / mt_getrandmax(), 1e-9);
        $v = mt_rand() / mt_getrandmax();

        return $mean + $deviation * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
