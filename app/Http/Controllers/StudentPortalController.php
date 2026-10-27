<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentPortalService;
use App\Services\VisualizationService;
use App\Support\Grading;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student's results portal (README §14.9).
 *
 * Published results only. A student is not told that unreleased results
 * exist at all; staff viewing a student's record see those sessions as
 * "awaiting release", still with no scores in the payload.
 */
class StudentPortalController extends Controller
{
    public function __construct(
        private readonly StudentPortalService $portal,
        private readonly VisualizationService $visualization,
    ) {}

    public function index(Request $request): Response
    {
        $student = $this->resolveStudent($request);

        return $this->render($student, $request);
    }

    /**
     * A specific student's results, for staff. Students reaching this route
     * for anyone but themselves are refused by the policy.
     */
    public function show(Request $request, Student $student): Response
    {
        $this->authorize('view', $student);

        return $this->render($student->load('department.faculty'), $request);
    }

    private function render(Student $student, Request $request): Response
    {
        $trend = $this->visualization->cgpaTrend($student->getKey());
        $cgpa = $trend === [] ? 0.0 : (float) end($trend)['cgpa'];
        $isOwnResults = $student->user_id === $request->user()->getKey();

        return Inertia::render('student/results', [
            'student' => [
                'id' => $student->getKey(),
                'name' => $student->name,
                'reg_number' => $student->reg_number,
                'department' => $student->department->dept_name,
                'faculty' => $student->department->faculty->faculty_name,
                'entry_year' => $student->entry_year,
            ],
            'standing' => [
                'cgpa' => round($cgpa, 2),
                'classification' => Grading::classification($cgpa),
                'credit_units_passed' => $this->portal->creditUnitsPassed($student),
            ],
            'sessions' => $this->portal->publishedResults($student),
            'awaitingRelease' => $isOwnResults ? [] : $this->portal->sessionsAwaitingRelease($student),
            'hasWithheldResults' => $this->portal->hasWithheldResults($student),
            'isOwnResults' => $isOwnResults,
        ]);
    }

    private function resolveStudent(Request $request): Student
    {
        return Student::query()
            ->with(['department.faculty'])
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();
    }
}
