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
 * Published results only. Sessions with results that exist but have not been
 * released appear as "awaiting release" and carry no scores in the payload.
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

        return Inertia::render('student/results', [
            'student' => [
                'id' => $student->getKey(),
                'name' => $student->user->name,
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
            'awaitingRelease' => $this->portal->sessionsAwaitingRelease($student),
            'hasWithheldResults' => $this->portal->hasWithheldResults($student),
            'isOwnResults' => $student->user_id === $request->user()->getKey(),
        ]);
    }

    private function resolveStudent(Request $request): Student
    {
        return Student::query()
            ->with(['department.faculty', 'user'])
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();
    }
}
