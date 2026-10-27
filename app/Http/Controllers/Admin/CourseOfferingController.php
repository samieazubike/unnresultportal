<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCourseOfferingRequest;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\RegistrationWindow;
use App\Models\Student;
use App\Services\CourseOfferingService;
use App\Support\AcademicLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The administrator's side of course registration: which courses each
 * department and level takes in a semester, and whether students may
 * currently change their own registration.
 */
class CourseOfferingController extends Controller
{
    public function __construct(private readonly CourseOfferingService $offerings) {}

    public function index(Request $request): Response
    {
        $this->authorize('manage', Course::class);

        $departments = Department::query()->orderBy('dept_name')->get(['id', 'dept_name']);

        $session = (string) $request->query('session', config('grading.current_session'));
        $semester = (int) $request->query('semester', 1);
        $level = (int) $request->query('level', AcademicLevel::LEVELS[0]);
        $deptId = (int) $request->query('dept_id', $departments->value('id', 0));

        return Inertia::render('admin/registration', [
            'filters' => [
                'dept_id' => $deptId,
                'level' => $level,
                'session' => $session,
                'semester' => $semester,
            ],
            'departments' => $departments,
            'levels' => AcademicLevel::LEVELS,
            'sessions' => $this->sessionChoices($session),
            'offeredCourseIds' => CourseOffering::query()
                ->where('dept_id', $deptId)
                ->where('level', $level)
                ->where('session', $session)
                ->where('semester', $semester)
                ->pluck('course_id'),
            'courses' => Course::query()
                ->with('department:id,dept_name')
                ->orderBy('course_code')
                ->get()
                ->map(fn (Course $course) => [
                    'id' => $course->id,
                    'course_code' => $course->course_code,
                    'course_title' => $course->course_title,
                    'credit_units' => $course->credit_units,
                    'dept_id' => $course->dept_id,
                    'department' => $course->department->dept_name,
                ]),
            'cohortSize' => Student::query()
                ->where('dept_id', $deptId)
                ->where('entry_year', AcademicLevel::entryYearFor($level, $session))
                ->count(),
            'windows' => collect([1, 2])->map(function (int $windowSemester) use ($session) {
                $window = RegistrationWindow::query()
                    ->with('updater:id,name')
                    ->where('session', $session)
                    ->where('semester', $windowSemester)
                    ->first();

                return [
                    'semester' => $windowSemester,
                    'is_open' => $window !== null && $window->is_open,
                    'updated_by' => $window?->updater?->name,
                    'updated_at' => $window?->updated_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    public function update(SaveCourseOfferingRequest $request): RedirectResponse
    {
        $this->authorize('manage', Course::class);

        $validated = $request->validated();

        $outcome = $this->offerings->sync(
            (int) $validated['dept_id'],
            (int) $validated['level'],
            $validated['session'],
            (int) $validated['semester'],
            $validated['course_ids'],
        );

        return back()->with('success', sprintf(
            'Offering saved. %d registration(s) added and %d removed across %d student(s).',
            $outcome['created'],
            $outcome['removed'],
            $outcome['students'],
        ));
    }

    /**
     * The sessions an administrator can pick: the year either side of the one
     * in view, plus any session that already has an offering or a window.
     *
     * @return list<string>
     */
    private function sessionChoices(string $session): array
    {
        $start = AcademicLevel::sessionStartYear($session);

        $choices = array_unique([
            ...array_map(fn (int $offset) => sprintf('%d/%d', $start + $offset, $start + $offset + 1), [-1, 0, 1]),
            ...CourseOffering::query()->distinct()->pluck('session')->map(fn (string $existing): string => $existing)->all(),
            ...RegistrationWindow::query()->distinct()->pluck('session')->map(fn (string $existing): string => $existing)->all(),
        ]);

        rsort($choices);

        return $choices;
    }
}
