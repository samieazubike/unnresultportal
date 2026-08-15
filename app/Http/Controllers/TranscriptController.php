<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentPortalService;
use App\Services\VisualizationService;
use App\Support\Grading;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Transcript download (README §14.12).
 *
 * Published results only, and a student may take their own alone — alumni
 * keep that access indefinitely (§3). Anyone requesting another student's
 * transcript by changing the URL is refused by StudentPolicy.
 */
class TranscriptController extends Controller
{
    public function __construct(
        private readonly StudentPortalService $portal,
        private readonly VisualizationService $visualization,
    ) {}

    public function __invoke(Request $request, ?Student $student = null): Response
    {
        $student = $student ?? Student::query()
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();

        $this->authorize('downloadTranscript', $student);

        $student->load(['department.faculty']);

        $sessions = $this->portal->publishedResults($student);
        $trend = $this->visualization->cgpaTrend($student->getKey());
        $cgpa = $trend === [] ? 0.0 : (float) end($trend)['cgpa'];

        $pdf = Pdf::loadView('transcripts.academic', [
            'student' => $student,
            'sessions' => $sessions,
            'cgpa' => round($cgpa, 2),
            'classification' => Grading::classification($cgpa),
            'creditUnitsPassed' => $this->portal->creditUnitsPassed($student),
            'totalCreditUnits' => array_sum(array_column($sessions, 'credit_units')),
            'generatedAt' => now(),
        ])->setPaper('a4');

        return $pdf->download($this->filename($student));
    }

    private function filename(Student $student): string
    {
        return sprintf('transcript-%s.pdf', str_replace('/', '-', $student->reg_number));
    }
}
