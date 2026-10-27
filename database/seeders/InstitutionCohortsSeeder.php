<?php

namespace Database\Seeders;

use App\Enums\ResultStatus;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Registration;
use App\Models\Result;
use App\Models\Student;
use App\Models\User;
use App\Services\CourseOfferingService;
use App\Services\GradingService;
use App\Services\ResultWorkflowService;
use App\Support\AcademicLevel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * An institution-wide dataset, so the administrator's, ICT director's,
 * examination officer's and lecturers' charts compare something.
 *
 * It adds two faculties and five departments to the original four, then
 * gives all ten departments a 2023, 2024 and 2025 intake. Each cohort is
 * registered through course offerings and has every semester from entry to
 * 2025/2026 first semester taken through the real workflow (RunsResultWorkflow).
 *
 * The shape is deliberate:
 *
 *  - each department has its own mean ability, so the department and
 *    faculty comparisons differ, with Engineering weakest and Social
 *    Sciences strongest
 *  - hard courses such as PHY301 and ELE222 run far above the failure
 *    threshold, so flagging fires outside Computer Science too
 *  - first-year courses are shared across departments, so MTH101 and
 *    PHY101 sheets have large mixed classes
 *  - COS101, COS201 and COS301, led by Dr. Okafor, are published for
 *    2025/2026, so the lecturer dashboard's current-session charts are full
 *  - three current-session sheets are left mid-workflow, so the review,
 *    publication and lecturer queues all have work on them
 *
 * Runs after DatabaseSeeder (staff) and ResultWorkflowDemoSeeder. Courses,
 * departments and students are matched before they are created and scored
 * registrations are skipped, so it is safe to re-run.
 *
 *   php artisan db:seed --class=InstitutionCohortsSeeder
 */
class InstitutionCohortsSeeder extends Seeder
{
    use RunsResultWorkflow;

    private const SESSIONS = ['2023/2024', '2024/2025', '2025/2026'];

    private const LIVE_SESSION = '2025/2026';

    private const ENTRY_YEARS = [2023, 2024, 2025];

    /**
     * Faculty => department => [course prefix, mean ability, students per
     * intake].
     *
     * @var array<string, array<string, array{0: string, 1: int, 2: int}>>
     */
    private const STRUCTURE = [
        'Faculty of Physical Sciences' => [
            'Computer Science' => ['COS', 58, 35],
            'Mathematics' => ['MTH', 55, 14],
            'Statistics' => ['STA', 61, 15],
            'Physics and Astronomy' => ['PHY', 53, 12],
            'Pure and Industrial Chemistry' => ['CHM', 56, 16],
        ],
        'Faculty of Engineering' => [
            'Electronic Engineering' => ['ELE', 51, 22],
            'Mechanical Engineering' => ['MEE', 50, 20],
        ],
        'Faculty of Biological Sciences' => [
            'Microbiology' => ['MCB', 60, 18],
            'Biochemistry' => ['BCH', 57, 17],
        ],
        'Faculty of Social Sciences' => [
            'Economics' => ['ECO', 63, 26],
        ],
    ];

    /**
     * Department => level => semester => course codes. 300L runs to first
     * semester only: that is where the 2023 intake is now.
     *
     * @var array<string, array<int, array<int, list<string>>>>
     */
    private const CURRICULUM = [
        'Computer Science' => [
            100 => [1 => ['COS101', 'MTH101', 'STA101'], 2 => ['COS102', 'MTH102', 'COS104']],
            200 => [1 => ['COS201', 'MTH201', 'COS203'], 2 => ['COS202', 'STA202', 'COS204']],
            300 => [1 => ['COS301', 'COS303', 'COS305']],
        ],
        'Mathematics' => [
            100 => [1 => ['MTH101', 'MTH111', 'PHY101'], 2 => ['MTH102', 'MTH112', 'PHY102']],
            200 => [1 => ['MTH201', 'MTH211', 'MTH221'], 2 => ['MTH202', 'MTH212', 'STA202']],
            300 => [1 => ['MTH301', 'MTH311', 'MTH321']],
        ],
        'Statistics' => [
            100 => [1 => ['STA101', 'MTH101', 'STA111'], 2 => ['STA102', 'MTH102', 'STA112']],
            200 => [1 => ['STA201', 'MTH201', 'STA211'], 2 => ['STA202', 'STA212', 'STA222']],
            300 => [1 => ['STA301', 'STA311', 'STA321']],
        ],
        'Physics and Astronomy' => [
            100 => [1 => ['PHY101', 'MTH101', 'PHY111'], 2 => ['PHY102', 'MTH102', 'PHY112']],
            200 => [1 => ['PHY201', 'MTH201', 'PHY211'], 2 => ['PHY202', 'PHY212', 'STA202']],
            300 => [1 => ['PHY301', 'PHY311', 'PHY321']],
        ],
        'Pure and Industrial Chemistry' => [
            100 => [1 => ['CHM101', 'MTH101', 'PHY101'], 2 => ['CHM102', 'MTH102', 'CHM112']],
            200 => [1 => ['CHM201', 'CHM211', 'CHM221'], 2 => ['CHM202', 'CHM212', 'STA202']],
            300 => [1 => ['CHM301', 'CHM311', 'CHM321']],
        ],
        'Electronic Engineering' => [
            100 => [1 => ['ELE101', 'MTH101', 'PHY101'], 2 => ['ELE102', 'MTH102', 'PHY102']],
            200 => [1 => ['ELE201', 'MTH201', 'ELE211'], 2 => ['ELE202', 'ELE212', 'ELE222']],
            300 => [1 => ['ELE301', 'ELE311', 'ELE321']],
        ],
        'Mechanical Engineering' => [
            100 => [1 => ['MEE101', 'MTH101', 'PHY101'], 2 => ['MEE102', 'MTH102', 'PHY102']],
            200 => [1 => ['MEE201', 'MTH201', 'MEE211'], 2 => ['MEE202', 'MEE212', 'MEE222']],
            300 => [1 => ['MEE301', 'MEE311', 'MEE321']],
        ],
        'Microbiology' => [
            100 => [1 => ['BIO101', 'CHM101', 'MTH101'], 2 => ['BIO102', 'CHM102', 'MCB102']],
            200 => [1 => ['MCB201', 'BCH201', 'MCB211'], 2 => ['MCB202', 'MCB212', 'STA202']],
            300 => [1 => ['MCB301', 'MCB311', 'MCB321']],
        ],
        'Biochemistry' => [
            100 => [1 => ['BIO101', 'CHM101', 'MTH101'], 2 => ['BIO102', 'CHM102', 'BCH102']],
            200 => [1 => ['BCH201', 'CHM211', 'BCH211'], 2 => ['BCH202', 'BCH212', 'STA202']],
            300 => [1 => ['BCH301', 'BCH311', 'BCH321']],
        ],
        'Economics' => [
            100 => [1 => ['ECO101', 'ECO111', 'STA101'], 2 => ['ECO102', 'ECO112', 'ECO122']],
            200 => [1 => ['ECO201', 'ECO211', 'ECO221'], 2 => ['ECO202', 'ECO212', 'ECO222']],
            300 => [1 => ['ECO301', 'ECO311', 'ECO321']],
        ],
    ];

    /**
     * Code => [title, credit units, owning department, difficulty].
     * Difficulty shifts the class mean; courses already in the catalogue
     * keep their stored title and units.
     *
     * @var array<string, array{0: string, 1: int, 2: string, 3: int}>
     */
    private const COURSES = [
        'COS101' => ['Introduction to Computer Science', 3, 'Computer Science', 4],
        'COS102' => ['Introduction to Problem Solving', 3, 'Computer Science', 2],
        'COS104' => ['Computer Applications', 2, 'Computer Science', 8],
        'COS201' => ['Computer Programming I', 3, 'Computer Science', 0],
        'COS202' => ['Computer Programming II', 3, 'Computer Science', -2],
        'COS203' => ['Discrete Structures', 3, 'Computer Science', -3],
        'COS204' => ['Computer Architecture', 3, 'Computer Science', 0],
        'COS301' => ['Operating Systems I', 3, 'Computer Science', -1],
        'COS303' => ['Data Structures and Algorithms', 3, 'Computer Science', -4],
        'COS305' => ['Systems Analysis and Design', 2, 'Computer Science', 5],

        'MTH101' => ['Elementary Mathematics I', 3, 'Mathematics', -4],
        'MTH102' => ['Elementary Mathematics II', 3, 'Mathematics', -6],
        'MTH111' => ['Elementary Set Theory', 2, 'Mathematics', 3],
        'MTH112' => ['Coordinate Geometry', 3, 'Mathematics', 0],
        'MTH201' => ['Mathematical Methods I', 3, 'Mathematics', -23],
        'MTH202' => ['Mathematical Methods II', 3, 'Mathematics', -6],
        'MTH211' => ['Abstract Algebra I', 3, 'Mathematics', -5],
        'MTH212' => ['Linear Algebra', 3, 'Mathematics', 2],
        'MTH221' => ['Real Analysis I', 3, 'Mathematics', -10],
        'MTH301' => ['Complex Analysis', 3, 'Mathematics', -4],
        'MTH311' => ['Numerical Analysis', 3, 'Mathematics', 3],
        'MTH321' => ['Ordinary Differential Equations', 3, 'Mathematics', -2],

        'STA101' => ['Introduction to Statistics', 2, 'Statistics', 0],
        'STA102' => ['Introduction to Probability', 3, 'Statistics', -2],
        'STA111' => ['Descriptive Statistics', 2, 'Statistics', 6],
        'STA112' => ['Statistical Computing I', 2, 'Statistics', 7],
        'STA201' => ['Probability Distributions', 3, 'Statistics', -3],
        'STA202' => ['Statistics for Physical Sciences', 2, 'Statistics', 1],
        'STA211' => ['Sampling Techniques', 2, 'Statistics', 4],
        'STA212' => ['Regression Analysis', 3, 'Statistics', 0],
        'STA222' => ['Design of Experiments', 3, 'Statistics', -1],
        'STA301' => ['Statistical Inference', 3, 'Statistics', -5],
        'STA311' => ['Time Series Analysis', 3, 'Statistics', 1],
        'STA321' => ['Operations Research', 2, 'Statistics', 4],

        'PHY101' => ['General Physics I', 3, 'Physics and Astronomy', -5],
        'PHY102' => ['General Physics II', 3, 'Physics and Astronomy', -6],
        'PHY111' => ['Physics Laboratory I', 2, 'Physics and Astronomy', 6],
        'PHY112' => ['Physics Laboratory II', 2, 'Physics and Astronomy', 5],
        'PHY201' => ['Classical Mechanics', 3, 'Physics and Astronomy', -8],
        'PHY202' => ['Thermal Physics', 3, 'Physics and Astronomy', -2],
        'PHY211' => ['Electricity and Magnetism I', 3, 'Physics and Astronomy', -4],
        'PHY212' => ['Waves and Optics', 3, 'Physics and Astronomy', 0],
        'PHY301' => ['Quantum Mechanics I', 3, 'Physics and Astronomy', -19],
        'PHY311' => ['Solid State Physics', 3, 'Physics and Astronomy', -3],
        'PHY321' => ['Electronics for Physicists', 2, 'Physics and Astronomy', 3],

        'CHM101' => ['General Chemistry I', 3, 'Pure and Industrial Chemistry', -2],
        'CHM102' => ['General Chemistry II', 3, 'Pure and Industrial Chemistry', -3],
        'CHM112' => ['Practical Chemistry I', 2, 'Pure and Industrial Chemistry', 6],
        'CHM201' => ['Inorganic Chemistry I', 3, 'Pure and Industrial Chemistry', -2],
        'CHM202' => ['Analytical Chemistry', 3, 'Pure and Industrial Chemistry', 0],
        'CHM211' => ['Organic Chemistry I', 3, 'Pure and Industrial Chemistry', -6],
        'CHM212' => ['Organic Chemistry II', 3, 'Pure and Industrial Chemistry', -8],
        'CHM221' => ['Physical Chemistry I', 3, 'Pure and Industrial Chemistry', -9],
        'CHM301' => ['Inorganic Chemistry II', 3, 'Pure and Industrial Chemistry', -3],
        'CHM311' => ['Spectroscopy', 3, 'Pure and Industrial Chemistry', -5],
        'CHM321' => ['Industrial Chemistry', 2, 'Pure and Industrial Chemistry', 4],

        'ELE101' => ['Introduction to Engineering', 2, 'Electronic Engineering', 6],
        'ELE102' => ['Basic Electrical Circuits', 3, 'Electronic Engineering', -1],
        'ELE201' => ['Circuit Theory I', 3, 'Electronic Engineering', -3],
        'ELE202' => ['Circuit Theory II', 3, 'Electronic Engineering', -4],
        'ELE211' => ['Electronic Devices', 3, 'Electronic Engineering', 0],
        'ELE212' => ['Digital Electronics', 3, 'Electronic Engineering', 2],
        'ELE222' => ['Electromagnetic Fields', 3, 'Electronic Engineering', -20],
        'ELE301' => ['Signals and Systems', 3, 'Electronic Engineering', -5],
        'ELE311' => ['Control Engineering', 3, 'Electronic Engineering', -2],
        'ELE321' => ['Communication Principles', 3, 'Electronic Engineering', 1],

        'MEE101' => ['Engineering Drawing I', 2, 'Mechanical Engineering', 4],
        'MEE102' => ['Workshop Practice', 2, 'Mechanical Engineering', 9],
        'MEE201' => ['Engineering Mechanics: Statics', 3, 'Mechanical Engineering', -4],
        'MEE202' => ['Engineering Mechanics: Dynamics', 3, 'Mechanical Engineering', -5],
        'MEE211' => ['Strength of Materials I', 3, 'Mechanical Engineering', -3],
        'MEE212' => ['Engineering Thermodynamics I', 3, 'Mechanical Engineering', -7],
        'MEE222' => ['Manufacturing Technology', 2, 'Mechanical Engineering', 5],
        'MEE301' => ['Fluid Mechanics I', 3, 'Mechanical Engineering', -6],
        'MEE311' => ['Machine Design I', 3, 'Mechanical Engineering', -2],
        'MEE321' => ['Heat Transfer', 3, 'Mechanical Engineering', -4],

        'BIO101' => ['General Biology I', 3, 'Microbiology', 3],
        'BIO102' => ['General Biology II', 3, 'Microbiology', 2],
        'MCB102' => ['Introductory Microbiology', 2, 'Microbiology', 5],
        'MCB201' => ['General Microbiology', 3, 'Microbiology', 1],
        'MCB202' => ['Microbial Physiology', 3, 'Microbiology', -2],
        'MCB211' => ['Microbiological Techniques', 2, 'Microbiology', 6],
        'MCB212' => ['Immunology I', 3, 'Microbiology', -3],
        'MCB301' => ['Medical Microbiology', 3, 'Microbiology', 0],
        'MCB311' => ['Food Microbiology', 3, 'Microbiology', 3],
        'MCB321' => ['Microbial Genetics', 3, 'Microbiology', -6],

        'BCH102' => ['Introduction to Biochemistry', 2, 'Biochemistry', 3],
        'BCH201' => ['General Biochemistry I', 3, 'Biochemistry', -4],
        'BCH202' => ['General Biochemistry II', 3, 'Biochemistry', -3],
        'BCH211' => ['Biochemical Methods', 2, 'Biochemistry', 4],
        'BCH212' => ['Enzymology', 3, 'Biochemistry', -5],
        'BCH301' => ['Carbohydrate Metabolism', 3, 'Biochemistry', -4],
        'BCH311' => ['Molecular Biology', 3, 'Biochemistry', -7],
        'BCH321' => ['Nutritional Biochemistry', 2, 'Biochemistry', 3],

        'ECO101' => ['Principles of Economics I', 3, 'Economics', 3],
        'ECO102' => ['Principles of Economics II', 3, 'Economics', 2],
        'ECO111' => ['Mathematics for Economists I', 3, 'Economics', -5],
        'ECO112' => ['Introduction to Accounting', 2, 'Economics', 4],
        'ECO122' => ['The Nigerian Economy', 2, 'Economics', 8],
        'ECO201' => ['Microeconomic Theory I', 3, 'Economics', -2],
        'ECO202' => ['Microeconomic Theory II', 3, 'Economics', -3],
        'ECO211' => ['Macroeconomic Theory I', 3, 'Economics', -3],
        'ECO212' => ['Macroeconomic Theory II', 3, 'Economics', -2],
        'ECO221' => ['Statistics for Economists', 3, 'Economics', -4],
        'ECO222' => ['History of Economic Thought', 2, 'Economics', 6],
        'ECO301' => ['Econometrics I', 3, 'Economics', -18],
        'ECO311' => ['Public Finance', 3, 'Economics', 2],
        'ECO321' => ['Development Economics', 3, 'Economics', 3],
    ];

    /**
     * How far each live-session sheet gets; everything else is published.
     *
     * @var array<string, ResultStatus>
     */
    private const LIVE_SHEETS = [
        'MTH101' => ResultStatus::Submitted,
        'ELE201' => ResultStatus::Approved,
        'STA301' => ResultStatus::Draft,
    ];

    private const LECTURERS_PER_DEPARTMENT = 3;

    /**
     * Students per intake, by department. Public so tests can shrink it.
     *
     * @var array<string, int>|null
     */
    public ?array $cohortSizes = null;

    public function __construct(
        private readonly CourseOfferingService $offerings,
        private readonly GradingService $grading,
        private readonly ResultWorkflowService $workflow,
    ) {}

    public function run(): void
    {
        $departments = $this->seedStructure();
        $courses = $this->seedCourses($departments);
        $cohort = $this->seedStudents($departments);

        // Faker draws from mt_rand too, so reseed once the people exist.
        mt_srand(2025);

        $officer = User::query()->where('email', 'examofficer@unn.edu.ng')->sole();
        $director = User::query()->where('email', 'ictdirector@unn.edu.ng')->sole();

        $means = collect(self::STRUCTURE)->collapse()->map(fn (array $department) => $department[1]);
        $abilities = $cohort->mapWithKeys(fn (Student $student) => [
            $student->id => $this->clamp($this->normal($means[$student->department->dept_name], 9), 30, 86),
        ]);

        foreach (self::SESSIONS as $session) {
            foreach ([1, 2] as $semester) {
                if ($session === self::LIVE_SESSION && $semester === 2) {
                    continue;
                }

                $codes = $this->offerSemester($departments, $courses, $session, $semester);
                $publishedOn = $this->publicationDate($session, $semester);

                foreach ($codes->values() as $index => $code) {
                    $registrations = Registration::query()
                        ->with('student')
                        ->where('course_id', $courses[$code]->id)
                        ->where('session', $session)
                        ->where('semester', $semester)
                        ->whereIn('student_id', $cohort->modelKeys())
                        ->whereDoesntHave('result')
                        ->get();

                    $this->runSheet(
                        $registrations,
                        $courses[$code],
                        $session === self::LIVE_SESSION ? (self::LIVE_SHEETS[$code] ?? ResultStatus::Published) : ResultStatus::Published,
                        fn (Student $student) => round($this->clamp(
                            $abilities[$student->id] + self::COURSES[$code][3] + $this->normal(0, 8),
                            12,
                            96,
                        )),
                        $publishedOn->copy()->addDays($index % 10),
                        $officer,
                        $index % 2 === 0 ? $officer : $director,
                    );
                }
            }
        }

        $this->stopActing();

        $this->command?->info(sprintf(
            '%d faculties, %d departments, %d students, %d courses; %d results published.',
            Faculty::query()->count(),
            Department::query()->count(),
            Student::query()->count(),
            Course::query()->count(),
            Result::query()->published()->count(),
        ));
    }

    /**
     * @return Collection<string, Department>
     */
    private function seedStructure(): Collection
    {
        $departments = collect();

        foreach (self::STRUCTURE as $facultyName => $deptNames) {
            $faculty = Faculty::query()->firstOrCreate(['faculty_name' => $facultyName]);

            foreach (array_keys($deptNames) as $deptName) {
                $departments[$deptName] = Department::query()->firstOrCreate(
                    ['dept_name' => $deptName],
                    ['faculty_id' => $faculty->id],
                );
            }
        }

        return $departments;
    }

    /**
     * Create any missing course, headed by one of its department's
     * lecturers with sometimes a colleague co-teaching.
     *
     * @param  Collection<string, Department>  $departments
     * @return Collection<string, Course>
     */
    private function seedCourses(Collection $departments): Collection
    {
        $lecturers = $departments->map(fn (Department $department, string $deptName) => $this->departmentLecturers($deptName));

        return collect(self::COURSES)->map(function (array $definition, string $code) use ($departments, $lecturers) {
            [$title, $units, $deptName] = $definition;

            $course = Course::query()->firstOrCreate(['course_code' => $code], [
                'course_title' => $title,
                'credit_units' => $units,
                'dept_id' => $departments[$deptName]->id,
            ]);

            if ($course->lecturer_id === null) {
                $team = $lecturers[$deptName]->shuffle()->take(fake()->randomElement([1, 2, 2]));

                // The head must be on the team; the composite foreign key
                // checks that at commit, so both writes share a transaction.
                DB::transaction(function () use ($course, $team) {
                    $course->lecturers()->sync($team->pluck('id')->all());
                    $course->forceFill(['lecturer_id' => $team->first()->id])->save();
                });
            }

            return $course->load('head');
        });
    }

    /**
     * The department's teaching staff, created on first run as
     * {prefix}.lecturerN@unn.edu.ng with the password "password".
     *
     * @return Collection<int, User>
     */
    private function departmentLecturers(string $deptName): Collection
    {
        $prefix = strtolower(collect(self::STRUCTURE)->collapse()[$deptName][0]);

        return collect(range(1, self::LECTURERS_PER_DEPARTMENT))->map(function (int $number) use ($prefix) {
            $email = "{$prefix}.lecturer{$number}@unn.edu.ng";

            return User::query()->where('email', $email)->first()
                ?? User::factory()->lecturer()->create(['email' => $email, 'name' => 'Dr. '.fake()->name()]);
        });
    }

    /**
     * Each department's intakes. Most students have claimed their account;
     * about one in seven is still an unclaimed roster row.
     *
     * Registration numbers are {entry year}/7{department}{sequence}, clear
     * of the ranges the other seeders use.
     *
     * @param  Collection<string, Department>  $departments
     * @return EloquentCollection<int, Student>
     */
    private function seedStudents(Collection $departments): EloquentCollection
    {
        $ids = [];

        foreach ($departments->keys()->values() as $deptIndex => $deptName) {
            $size = $this->cohortSizes[$deptName] ?? collect(self::STRUCTURE)->collapse()[$deptName][2];

            foreach (self::ENTRY_YEARS as $entryYear) {
                for ($number = 1; $number <= $size; $number++) {
                    $regNumber = sprintf('%d/7%d%04d', $entryYear, $deptIndex, $number);

                    $student = Student::query()->where('reg_number', $regNumber)->first();

                    if ($student === null) {
                        $user = fake()->boolean(86) ? User::factory()->student()->create() : null;

                        $student = Student::query()->create([
                            'user_id' => $user?->id,
                            'name' => $user->name ?? fake()->name(),
                            'reg_number' => $regNumber,
                            'dept_id' => $departments[$deptName]->id,
                            'entry_year' => $entryYear,
                        ]);
                    }

                    $ids[] = $student->id;
                }
            }
        }

        return Student::query()->with('department')->whereKey($ids)->get();
    }

    /**
     * Save every department's offering for the levels it has an intake at
     * this semester, and return the course codes on offer.
     *
     * @param  Collection<string, Department>  $departments
     * @param  Collection<string, Course>  $courses
     * @return Collection<int, string>
     */
    private function offerSemester(Collection $departments, Collection $courses, string $session, int $semester): Collection
    {
        $codes = collect();

        foreach (self::CURRICULUM as $deptName => $levels) {
            foreach (self::ENTRY_YEARS as $entryYear) {
                $level = (AcademicLevel::sessionStartYear($session) - $entryYear + 1) * 100;
                $sheet = $levels[$level][$semester] ?? null;

                if ($level < 100 || $sheet === null) {
                    continue;
                }

                $this->offerings->sync(
                    $departments[$deptName]->id,
                    $level,
                    $session,
                    $semester,
                    array_map(fn (string $code) => $courses[$code]->id, $sheet),
                );

                $codes->push(...$sheet);
            }
        }

        return $codes->unique()->values();
    }
}
