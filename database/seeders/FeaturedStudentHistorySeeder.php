<?php

namespace Database\Seeders;

use App\Enums\RegistrationSource;
use App\Enums\ResultStatus;
use App\Models\Course;
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

/**
 * A complete academic record for one featured student, so every chart on
 * their dashboard and results page has a story to tell.
 *
 * The student is placed in the 2023 intake their registration number names,
 * which makes 2025/2026 their 300L session. Five semesters are then
 * published, each course registered through a course offering and taken
 * through the real workflow — scored by the course head, submitted,
 * approved and published — on the dates it would have happened.
 *
 * The scores are fixed rather than random so the trend reads the same on
 * every run: a solid 100L, a dip at 200L first semester (a D in Discrete
 * Structures and an F in MTH201), recovery, and a strong 300L first
 * semester that includes passing MTH201 as a carryover.
 *
 * Runs after ResultWorkflowDemoSeeder, which creates the course catalogue.
 * Courses already scored for the student are skipped, so it is safe to
 * re-run.
 *
 *   php artisan db:seed --class=FeaturedStudentHistorySeeder
 */
class FeaturedStudentHistorySeeder extends Seeder
{
    use RunsResultWorkflow;

    public const EMAIL = 'samieazubike@gmail.com';

    private const ENTRY_YEAR = 2023;

    /**
     * Session => semester => course code => total score.
     *
     * @var array<string, array<int, array<string, int>>>
     */
    private const HISTORY = [
        '2023/2024' => [
            1 => ['COS101' => 68, 'MTH101' => 55, 'STA101' => 61],
            2 => ['COS102' => 71, 'MTH102' => 52, 'COS104' => 78],
        ],
        '2024/2025' => [
            1 => ['COS201' => 64, 'MTH201' => 34, 'COS203' => 47],
            2 => ['COS202' => 69, 'STA202' => 63, 'COS204' => 66],
        ],
        '2025/2026' => [
            1 => ['COS301' => 74, 'COS303' => 70, 'COS305' => 81, 'MTH201' => 58],
        ],
    ];

    /**
     * Courses the student registered for themselves — retakes of a failed
     * course — rather than receiving through the semester's offering.
     *
     * @var array<string, array<int, list<string>>>
     */
    private const CARRYOVERS = [
        '2025/2026' => [1 => ['MTH201']],
    ];

    public function __construct(
        private readonly CourseOfferingService $offerings,
        private readonly GradingService $grading,
        private readonly ResultWorkflowService $workflow,
    ) {}

    public function run(): void
    {
        mt_srand(2023);

        $student = Student::query()
            ->whereHas('user', fn ($query) => $query->where('email', self::EMAIL))
            ->sole();

        $student->update(['entry_year' => self::ENTRY_YEAR]);

        $officer = User::query()->where('email', 'examofficer@unn.edu.ng')->sole();
        $director = User::query()->where('email', 'ictdirector@unn.edu.ng')->sole();

        $courses = Course::query()
            ->with('head')
            ->whereIn('course_code', collect(self::HISTORY)->flatten(1)->flatMap(fn (array $sheet) => array_keys($sheet))->unique())
            ->get()
            ->keyBy('course_code');

        foreach (self::HISTORY as $session => $semesters) {
            foreach ($semesters as $semester => $scores) {
                $carryovers = self::CARRYOVERS[$session][$semester] ?? [];

                $this->offerings->sync(
                    $student->dept_id,
                    AcademicLevel::forStudent($student, $session),
                    $session,
                    $semester,
                    collect($scores)->except($carryovers)->keys()->map(fn (string $code) => $courses[$code]->id)->all(),
                );

                foreach ($carryovers as $code) {
                    Registration::query()->firstOrCreate([
                        'student_id' => $student->id,
                        'course_id' => $courses[$code]->id,
                        'session' => $session,
                        'semester' => $semester,
                    ], ['source' => RegistrationSource::Student]);
                }

                $publishedOn = $this->publicationDate($session, $semester);

                foreach (array_keys($scores) as $index => $code) {
                    $this->publish(
                        $student,
                        $courses[$code],
                        $session,
                        $semester,
                        $scores[$code],
                        $publishedOn->copy()->addDays($index * 2),
                        $officer,
                        $index % 2 === 0 ? $officer : $director,
                    );
                }
            }
        }

        $this->stopActing();

        // Advice cached against the old, near-empty record is stale.
        Recommendation::query()->where('student_id', $student->id)->delete();

        $this->command?->info(sprintf(
            '%s now has %d published results.',
            self::EMAIL,
            Result::query()->published()->whereHas('registration', fn ($query) => $query->where('student_id', $student->id))->count(),
        ));
    }

    /**
     * Take the student's result for one course from score entry to
     * publication.
     */
    private function publish(
        Student $student,
        Course $course,
        string $session,
        int $semester,
        int $total,
        Carbon $publishedOn,
        User $officer,
        User $publisher,
    ): void {
        $registration = Registration::query()
            ->with(['student', 'result'])
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->where('session', $session)
            ->where('semester', $semester)
            ->sole();

        if ($registration->result !== null) {
            return;
        }

        $this->runSheet(
            new Collection([$registration]),
            $course,
            ResultStatus::Published,
            fn () => (float) $total,
            $publishedOn,
            $officer,
            $publisher,
        );
    }
}
