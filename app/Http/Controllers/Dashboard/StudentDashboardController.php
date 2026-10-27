<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\RecommendationService;
use App\Services\StudentPortalService;
use App\Services\VisualizationService;
use App\Support\Grading;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student's own dashboard: CGPA trend, standing, and the generated
 * recommendation. Everything here reads published results only.
 */
class StudentDashboardController extends Controller
{
    public function __construct(
        private readonly VisualizationService $visualization,
        private readonly RecommendationService $recommendations,
        private readonly StudentPortalService $portal,
    ) {}

    public function __invoke(Request $request): Response
    {
        $student = Student::query()
            ->with('department.faculty')
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();

        $trend = $this->visualization->cgpaTrend($student->getKey());
        $cgpa = $trend === [] ? 0.0 : (float) end($trend)['cgpa'];

        return Inertia::render('student/dashboard', [
            'student' => [
                'name' => $request->user()->name,
                'reg_number' => $student->reg_number,
                'department' => $student->department->dept_name,
                'faculty' => $student->department->faculty->faculty_name,
                'entry_year' => $student->entry_year,
            ],
            'standing' => [
                'cgpa' => round($cgpa, 2),
                'classification' => Grading::classification($cgpa),
                'semesters_completed' => count($trend),
                'credit_units_passed' => $this->portal->creditUnitsPassed($student),
            ],
            'cgpaTrend' => $trend,
            'semesterGpas' => $this->visualization->semesterGpas($student->getKey()),

            // Deferred: the recommendation may call a language model, and the
            // dashboard should paint before it resolves.
            'recommendation' => Inertia::defer(fn () => $this->recommendationPayload($student)),
        ]);
    }

    /**
     * @return array{body: string, source: string, flags: list<string>, generated_at: string}
     */
    private function recommendationPayload(Student $student): array
    {
        $recommendation = $this->recommendations->forStudent(
            $student,
            (string) config('grading.current_session'),
        );

        return [
            'body' => $recommendation->body,
            'source' => $recommendation->source,
            'flags' => $recommendation->flags,
            'generated_at' => $recommendation->generated_at->toIso8601String(),
        ];
    }
}
