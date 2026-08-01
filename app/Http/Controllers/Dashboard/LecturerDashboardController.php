<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ResultStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Result;
use App\Services\VisualizationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The lecturer's dashboard (README §3: "view own course grade distribution").
 *
 * Courses are split into those the lecturer leads and those they co-teach —
 * only the former carry the responsibility of submitting a sheet.
 */
class LecturerDashboardController extends Controller
{
    public function __construct(private readonly VisualizationService $visualization) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $session = (string) $request->query('session', config('grading.current_session'));

        $courses = $user->taughtCourses()->with('department')->get();
        $courseIds = $courses->pluck('id');

        $statusCounts = Result::query()
            ->whereHas('registration', fn ($query) => $query->whereIn('course_id', $courseIds))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $selectedCourseId = (int) $request->query('course', $courses->first()?->id ?? 0);
        $selectedCourse = $courses->firstWhere('id', $selectedCourseId) ?? $courses->first();

        return Inertia::render('lecturer/dashboard', [
            'summary' => [
                'courses' => $courses->count(),
                'courses_led' => $courses->filter(fn (Course $course) => $course->isHeadedBy($user))->count(),
                'drafts' => (int) ($statusCounts[ResultStatus::Draft->value] ?? 0),
                'submitted' => (int) ($statusCounts[ResultStatus::Submitted->value] ?? 0),
                'approved' => (int) ($statusCounts[ResultStatus::Approved->value] ?? 0),
                'published' => (int) ($statusCounts[ResultStatus::Published->value] ?? 0),
            ],
            'leading' => $courses->filter(fn (Course $course) => $course->isHeadedBy($user))
                ->map(fn (Course $course) => $this->courseSummary($course, true))
                ->values(),
            'coTeaching' => $courses->reject(fn (Course $course) => $course->isHeadedBy($user))
                ->map(fn (Course $course) => $this->courseSummary($course, false))
                ->values(),
            'selectedCourse' => $selectedCourse ? [
                'id' => $selectedCourse->id,
                'course_code' => $selectedCourse->course_code,
                'course_title' => $selectedCourse->course_title,
            ] : null,
            'gradeDistribution' => $selectedCourse
                ? $this->visualization->gradeDistribution($selectedCourse->id, $session)
                : [],
            'failureRates' => array_values(array_filter(
                $this->visualization->courseFailureRates($session),
                fn (array $row) => $courses->contains('course_code', $row['course_code']),
            )),
            'session' => $session,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function courseSummary(Course $course, bool $isHead): array
    {
        return [
            'id' => $course->id,
            'course_code' => $course->course_code,
            'course_title' => $course->course_title,
            'credit_units' => $course->credit_units,
            'department' => $course->department->dept_name,
            'is_head' => $isHead,
        ];
    }
}
