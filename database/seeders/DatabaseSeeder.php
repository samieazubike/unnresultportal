<?php

namespace Database\Seeders;

use App\Enums\ResultStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Registration;
use App\Models\Result;
use App\Models\ResultSignature;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Support\Grading;
use App\Support\ResultIntegrity;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * A simulated UNN dataset (README §12).
 *
 * Real result data was not available, so this must stand in for it
 * convincingly: "a demo where every chart is flat or empty is worse than no
 * demo". The shape is deliberate —
 *
 *   - scores cluster around C and B with a realistic failure tail, never
 *     uniform, so the distribution chart has a recognisable curve
 *   - two courses are seeded above the failure threshold so the flagging
 *     feature demonstrably fires
 *   - one student is given a steadily declining CGPA so the recommendation
 *     layer has a real case to speak to
 *   - several sessions of history so the CGPA trend has something to plot
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const SESSIONS = ['2020/2021', '2021/2022', '2022/2023', '2023/2024', '2024/2025', '2025/2026'];

    /** Courses deliberately pushed above the failure threshold. */
    private const NOTORIOUS_COURSES = ['MTH 201', 'COS 302'];

    /** The session left mid-workflow so every review screen has work on it. */
    private const LIVE_SESSION = '2025/2026';

    public function run(): void
    {
        Setting::put(Setting::FAILURE_THRESHOLD, 40);

        $departments = $this->seedFacultiesAndDepartments();
        $lecturers = $this->seedStaff();
        // $courses = $this->seedCourses($departments, $lecturers);
        // $students = $this->seedStudents($departments);

        // $this->seedResults($students, $courses);
        // $this->seedDecliningStudent($departments['Computer Science'], $courses);
        // $this->seedWorkInProgress($students, $courses);

        // $this->command?->info(sprintf(
        //     'Seeded %d students, %d courses, %d results (%d awaiting review, %d awaiting release).',
        //     Student::count(),
        //     Course::count(),
        //     Result::count(),
        //     Result::where('status', ResultStatus::Submitted)->count(),
        //     Result::where('status', ResultStatus::Approved)->count(),
        // ));
    }

    /**
     * @return array<string, Department>
     */
    private function seedFacultiesAndDepartments(): array
    {
        $structure = [
            'Physical Sciences' => ['Computer Science', 'Mathematics', 'Statistics'],
            'Engineering' => ['Electronic Engineering'],
        ];

        $departments = [];

        foreach ($structure as $facultyName => $deptNames) {
            $faculty = Faculty::query()->create(['faculty_name' => "Faculty of {$facultyName}"]);

            foreach ($deptNames as $deptName) {
                $departments[$deptName] = Department::query()->create([
                    'dept_name' => $deptName,
                    'faculty_id' => $faculty->id,
                ]);
            }
        }

        return $departments;
    }

    /**
     * Named accounts for the demo, one per role, all with the password
     * "password" — plus a pool of lecturers to staff the courses.
     *
     * @return list<User>
     */
    private function seedStaff(): array
    {
        foreach ([
            ['Admin User', 'admin@unn.edu.ng', UserRole::Admin],
            ['Exam Officer', 'examofficer@unn.edu.ng', UserRole::ExamOfficer],
            ['ICT Director', 'ictdirector@unn.edu.ng', UserRole::IctDirector],
        ] as [$name, $email, $role]) {
            User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => $role,
                'email_verified_at' => now(),
            ]);
        }

        $namedLecturer = User::query()->create([
            'name' => 'Dr. Chinedu Okafor',
            'email' => 'lecturer@unn.edu.ng',
            'password' => Hash::make('password'),
            'role' => UserRole::Lecturer,
            'email_verified_at' => now(),
        ]);

        return [
            $namedLecturer,
            ...User::factory()->count(11)->lecturer()->create()->all(),
        ];
    }

    /**
     * 48 courses with plausible codes and 2 or 3 credit units, each staffed by
     * a teaching team of one to three lecturers with a designated head.
     *
     * @param  array<string, Department>  $departments
     * @param  list<User>  $lecturers
     * @return list<Course>
     */
    private function seedCourses(array $departments, array $lecturers): array
    {
        $prefixes = [
            'Computer Science' => 'COS',
            'Mathematics' => 'MTH',
            'Statistics' => 'STA',
            'Electronic Engineering' => 'ELE',
        ];

        $titles = [
            'Introduction to', 'Principles of', 'Advanced', 'Applied',
            'Foundations of', 'Topics in', 'Analysis of',
        ];

        $subjects = [
            'Programming', 'Algorithms', 'Data Structures', 'Operating Systems',
            'Computer Networks', 'Database Systems', 'Software Engineering',
            'Discrete Mathematics', 'Linear Algebra', 'Probability', 'Calculus',
        ];

        $courses = [];

        foreach ($prefixes as $deptName => $prefix) {
            foreach ([1, 2, 3, 4] as $level) {
                foreach (range(1, 3) as $index) {
                    $course = Course::query()->create([
                        'course_code' => sprintf('%s %d0%d', $prefix, $level, $index),
                        'course_title' => fake()->randomElement($titles).' '.fake()->randomElement($subjects),
                        'credit_units' => fake()->randomElement([2, 3]),
                        'dept_id' => $departments[$deptName]->id,
                        'lecturer_id' => null,
                    ]);

                    $this->staffCourse($course, $lecturers);

                    $courses[] = $course;
                }
            }
        }

        return $courses;
    }

    /**
     * Assign a teaching team. Most courses get two or three lecturers so the
     * head/co-lecturer distinction is visible in the demo; some keep a single
     * lecturer, who is then automatically the head.
     *
     * The head must be a member of the team, and the database enforces that
     * with a deferred composite foreign key, so both writes happen in one
     * transaction.
     *
     * @param  list<User>  $lecturers
     */
    private function staffCourse(Course $course, array $lecturers): void
    {
        $team = fake()->randomElements($lecturers, fake()->randomElement([1, 2, 2, 3]));

        DB::transaction(function () use ($course, $team) {
            $course->lecturers()->sync(array_map(fn (User $user) => $user->id, $team));
            $course->forceFill(['lecturer_id' => $team[0]->id])->save();
        });
    }

    /**
     * 220 students across four entry years.
     *
     * @param  array<string, Department>  $departments
     * @return list<Student>
     */
    private function seedStudents(array $departments): array
    {
        $students = [];
        $sequence = 100;

        foreach ([2021, 2022, 2023, 2024] as $entryYear) {
            foreach ($departments as $department) {
                $count = $department->dept_name === 'Computer Science' ? 25 : 10;

                for ($i = 0; $i < $count; $i++) {
                    $user = User::factory()->student()->create();

                    $students[] = Student::query()->create([
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'reg_number' => sprintf('%d/%06d', $entryYear, $sequence++),
                        'dept_id' => $department->id,
                        'entry_year' => $entryYear,
                    ]);
                }
            }
        }

        return $students;
    }

    /**
     * Register each student for their department's courses across the
     * sessions they have been enrolled for, and publish the results.
     *
     * @param  list<Student>  $students
     * @param  list<Course>  $courses
     */
    private function seedResults(array $students, array $courses): void
    {
        $publisher = User::query()->where('email', 'examofficer@unn.edu.ng')->sole();
        $byDepartment = collect($courses)->groupBy('dept_id');

        foreach ($students as $student) {
            $deptCourses = $byDepartment->get($student->dept_id, collect())->values();
            $sessions = $this->sessionsFor($student->entry_year);

            foreach ($sessions as $sessionIndex => $session) {
                foreach ([1, 2] as $semester) {
                    $offset = (($sessionIndex * 2 + $semester - 1) * 3) % max(1, $deptCourses->count());

                    foreach ($deptCourses->slice($offset, 3) as $course) {
                        $this->createPublishedResult($student, $course, $session, $semester, $publisher);
                    }
                }
            }
        }
    }

    /**
     * The sessions a student of the given entry year has results for.
     *
     * @return list<string>
     */
    private function sessionsFor(int $entryYear): array
    {
        $startIndex = max(0, $entryYear - 2021);

        return array_values(array_slice(self::SESSIONS, $startIndex, 4));
    }

    private function createPublishedResult(
        Student $student,
        Course $course,
        string $session,
        int $semester,
        User $publisher,
    ): void {
        $registration = Registration::query()->firstOrCreate([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'session' => $session,
            'semester' => $semester,
        ]);

        if ($registration->result()->exists()) {
            return;
        }

        $this->storeResult(
            $registration,
            $this->realisticScore($course->course_code),
            $publisher,
        );
    }

    /**
     * A score drawn from a distribution that clusters around C and B with a
     * realistic tail of failures — never uniform (§12).
     *
     * The two notorious courses are shifted downward hard enough to push
     * their failure rate past the 40% threshold, so the flagging feature has
     * something to catch.
     */
    private function realisticScore(string $courseCode): float
    {
        if (in_array($courseCode, self::NOTORIOUS_COURSES, strict: true)) {
            // Roughly half the class lands below 40.
            $band = fake()->randomElement([
                ...array_fill(0, 50, 'F'),
                ...array_fill(0, 25, 'E_D'),
                ...array_fill(0, 20, 'C'),
                ...array_fill(0, 5, 'A'),
            ]);
        } else {
            $band = fake()->randomElement([
                ...array_fill(0, 8, 'F'),
                ...array_fill(0, 7, 'E_D'),
                ...array_fill(0, 30, 'C'),
                ...array_fill(0, 33, 'B'),
                ...array_fill(0, 22, 'A'),
            ]);
        }

        return (float) match ($band) {
            'F' => fake()->numberBetween(22, 39),
            'E_D' => fake()->numberBetween(40, 49),
            'C' => fake()->numberBetween(50, 59),
            'B' => fake()->numberBetween(60, 69),
            default => fake()->numberBetween(70, 92),
        };
    }

    /**
     * One student whose CGPA declines steadily, so the recommendation layer
     * has a genuine at-risk case rather than a synthetic one (§12).
     *
     * @param  list<Course>  $courses
     */
    private function seedDecliningStudent(Department $department, array $courses): void
    {
        $publisher = User::query()->where('email', 'examofficer@unn.edu.ng')->sole();

        $user = User::query()->create([
            'name' => 'Amaka Eze',
            'email' => 'student@unn.edu.ng',
            'password' => Hash::make('password'),
            'role' => UserRole::Student,
            'email_verified_at' => now(),
        ]);

        $student = Student::query()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'reg_number' => '2021/000001',
            'dept_id' => $department->id,
            'entry_year' => 2021,
        ]);

        $deptCourses = collect($courses)->where('dept_id', $department->id)->values();

        // Each successive semester is scored lower than the last.
        $ceilings = [88, 82, 74, 68, 61, 55, 48, 42];
        $slot = 0;

        foreach (['2021/2022', '2022/2023', '2023/2024', '2024/2025'] as $sessionIndex => $session) {
            foreach ([1, 2] as $semester) {
                $ceiling = $ceilings[$slot] ?? 40;
                $offset = (($sessionIndex * 2 + $semester - 1) * 3) % max(1, $deptCourses->count());

                foreach ($deptCourses->slice($offset, 3) as $course) {
                    $registration = Registration::query()->firstOrCreate([
                        'student_id' => $student->id,
                        'course_id' => $course->id,
                        'session' => $session,
                        'semester' => $semester,
                    ]);

                    if ($registration->result()->exists()) {
                        continue;
                    }

                    $this->storeResult(
                        $registration,
                        (float) fake()->numberBetween(max(20, $ceiling - 8), $ceiling),
                        $publisher,
                    );
                }

                $slot++;
            }
        }
    }

    /**
     * Leave the live session mid-workflow so every screen has real work on it:
     * sheets still with the lecturer, sheets awaiting the examination
     * officer's approval, and approved sheets awaiting release.
     *
     * That last group is the one that matters most for the demo — it is what
     * lets a student see "awaiting release" while an approved result exists
     * but has deliberately not been published (§9).
     *
     * @param  list<Student>  $students
     * @param  list<Course>  $courses
     */
    private function seedWorkInProgress(array $students, array $courses): void
    {
        $byDepartment = collect($courses)->groupBy('dept_id');
        $cohort = collect($students)->take(60);

        foreach ($cohort as $index => $student) {
            $deptCourses = $byDepartment->get($student->dept_id, collect())->values();

            // Three even groups: draft, submitted, approved-but-unpublished.
            $status = match ($index % 3) {
                0 => ResultStatus::Draft,
                1 => ResultStatus::Submitted,
                default => ResultStatus::Approved,
            };

            foreach ($deptCourses->slice(0, 3) as $course) {
                $registration = Registration::query()->firstOrCreate([
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'session' => self::LIVE_SESSION,
                    'semester' => 2,
                ]);

                if ($registration->result()->exists()) {
                    continue;
                }

                $total = $this->realisticScore($course->course_code);
                $ca = min(Grading::MAX_CA_SCORE, round($total * 0.28, 1));
                $band = Grading::forScore($total);

                $result = Result::query()->create([
                    'registration_id' => $registration->id,
                    'ca_score' => $ca,
                    'exam_score' => round($total - $ca, 1),
                    'total_score' => $total,
                    'grade' => $band['grade'],
                    'grade_point' => $band['grade_point'],
                    'status' => $status,
                    'published_by' => null,
                ]);

                // Approved sheets carry a frozen signature, as they would
                // have on their way through the examination officer.
                if ($status === ResultStatus::Approved) {
                    $this->signResult($result, User::query()->where('email', 'examofficer@unn.edu.ng')->sole());
                }
            }
        }
    }

    /**
     * Persist a published result, splitting the total across CA and exam and
     * deriving the grade through Grading — never inventing one here.
     */
    private function storeResult(Registration $registration, float $total, User $publisher): void
    {
        $ca = min(Grading::MAX_CA_SCORE, round($total * fake()->randomFloat(2, 0.26, 0.30), 1));
        $band = Grading::forScore($total);

        $result = Result::query()->create([
            'registration_id' => $registration->id,
            'ca_score' => $ca,
            'exam_score' => round($total - $ca, 1),
            'total_score' => $total,
            'grade' => $band['grade'],
            'grade_point' => $band['grade_point'],
            'status' => ResultStatus::Published,
            'published_by' => $publisher->id,
        ]);

        $this->signResult($result, $publisher);
    }

    /**
     * Seeded results carry a frozen signature, exactly as they would had they
     * travelled through approval — otherwise the integrity check would treat
     * the whole demo dataset as unsigned.
     */
    private function signResult(Result $result, User $publisher): void
    {
        ResultSignature::query()->create([
            'result_id' => $result->id,
            'integrity_hash' => ResultIntegrity::hash($result),
            'algo' => ResultIntegrity::ALGO,
            'frozen' => true,
            'signed_at' => now(),
            'signed_by' => $publisher->id,
        ]);
    }
}
