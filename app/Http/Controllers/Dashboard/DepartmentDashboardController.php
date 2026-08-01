<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Registration;
use App\Models\Setting;
use App\Services\VisualizationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Departmental and faculty dashboards for the examination officer, the ICT
 * director and the administrator (README §7 capability 3, §14.10).
 */
class DepartmentDashboardController extends Controller
{
    public function __construct(private readonly VisualizationService $visualization) {}

    public function __invoke(Request $request): Response
    {
        $session = $request->query('session');
        $session = is_string($session) && $session !== '' ? $session : null;

        $courseId = (int) $request->query('course', 0);
        $course = $courseId > 0 ? Course::query()->find($courseId) : Course::query()->first();

        return Inertia::render('staff/dashboard', [
            'summary' => $this->visualization->institutionSummary(),
            'departmentPerformance' => $this->visualization->departmentPerformance(),
            'facultyPerformance' => $this->visualization->facultyPerformance(),
            'failureRates' => $this->visualization->courseFailureRates($session),
            'failureThreshold' => Setting::failureThreshold(),
            'selectedCourse' => $course ? [
                'id' => $course->id,
                'course_code' => $course->course_code,
                'course_title' => $course->course_title,
            ] : null,
            'gradeDistribution' => $course
                ? $this->visualization->gradeDistribution($course->id, $session)
                : [],
            'courses' => Course::query()
                ->orderBy('course_code')
                ->get(['id', 'course_code', 'course_title']),
            'sessions' => $this->sessions(),
            'session' => $session,
            'canPublish' => $request->user()->canPublishResults(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function sessions(): array
    {
        return Registration::query()
            ->distinct()
            ->orderByDesc('session')
            ->pluck('session')
            ->all();
    }
}
