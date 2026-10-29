<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentPortalService;
use App\Services\VisualizationService;
use App\Support\Grading;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One semester's published results, on screen and as a downloadable
 * statement of result.
 *
 * Built from the same published-results set as the portal and transcript,
 * so an unreleased or withheld semester is simply not found: the response
 * is a 404, never a hint that results exist. Sessions travel in the URL as
 * 2025-2026 because the stored form contains a slash.
 */
class SemesterResultController extends Controller
{
    public function __construct(
        private readonly StudentPortalService $portal,
        private readonly VisualizationService $visualization,
    ) {}

    public function show(Request $request, string $session, int $semester): Response
    {
        return $this->render($this->ownStudent($request), $request, $session, $semester);
    }

    public function showForStudent(Request $request, Student $student, string $session, int $semester): Response
    {
        $this->authorize('view', $student);

        return $this->render($student->load('department.faculty'), $request, $session, $semester);
    }

    public function download(Request $request, string $session, int $semester): HttpResponse
    {
        return $this->pdf($this->ownStudent($request), $session, $semester);
    }

    public function downloadForStudent(Student $student, string $session, int $semester): HttpResponse
    {
        $this->authorize('downloadTranscript', $student);

        return $this->pdf($student->load('department.faculty'), $session, $semester);
    }

    private function render(Student $student, Request $request, string $session, int $semester): Response
    {
        $result = $this->semester($student, $session, $semester);

        return Inertia::render('student/semester-result', [
            'student' => [
                'id' => $student->getKey(),
                'name' => $student->name,
                'reg_number' => $student->reg_number,
                'department' => $student->department->dept_name,
                'faculty' => $student->department->faculty->faculty_name,
                'entry_year' => $student->entry_year,
            ],
            'semester' => $result,
            'cgpa' => $this->cgpaAsAt($student, $result['session'], $semester),
            'isOwnResults' => $student->user_id === $request->user()->getKey(),
        ]);
    }

    private function pdf(Student $student, string $session, int $semester): HttpResponse
    {
        $result = $this->semester($student, $session, $semester);
        $cgpa = $this->cgpaAsAt($student, $result['session'], $semester);

        return Pdf::loadView('transcripts.semester', [
            'student' => $student,
            'semester' => $result,
            'cgpa' => $cgpa,
            'classification' => Grading::classification($cgpa),
            'generatedAt' => now(),
        ])->setPaper('a4')->download(sprintf(
            'result-%s-%s-semester-%d.pdf',
            str_replace('/', '-', $student->reg_number),
            str_replace('/', '-', $result['session']),
            $semester,
        ));
    }

    /**
     * The published results for one semester, or a 404.
     *
     * @return array{session: string, semester: int, label: string, gpa: float, credit_units: int, courses: list<array<string, mixed>>}
     */
    private function semester(Student $student, string $session, int $semester): array
    {
        $session = str_replace('-', '/', $session);

        $result = collect($this->portal->publishedResults($student))
            ->first(fn (array $entry) => $entry['session'] === $session && $entry['semester'] === $semester);

        abort_if($result === null, 404);

        return $result;
    }

    /**
     * The running CGPA at the end of the given semester.
     */
    private function cgpaAsAt(Student $student, string $session, int $semester): float
    {
        $point = collect($this->visualization->cgpaTrend($student->getKey()))
            ->first(fn (array $entry) => $entry['session'] === $session && $entry['semester'] === $semester);

        return (float) ($point['cgpa'] ?? 0.0);
    }

    private function ownStudent(Request $request): Student
    {
        return Student::query()
            ->with(['department.faculty'])
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();
    }
}
