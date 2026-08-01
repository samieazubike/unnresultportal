<?php

namespace App\Http\Controllers;

use App\Enums\ResultStatus;
use App\Http\Requests\StoreScoresRequest;
use App\Models\Course;
use App\Models\Registration;
use App\Models\Result;
use App\Services\GradingService;
use App\Services\ResultWorkflowService;
use App\Services\ScoreSheetTemplate;
use App\Support\Grading;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Typed score entry (README §10, §14.5).
 *
 * Any lecturer on the course's teaching team may enter and correct scores.
 * Only the head may submit the sheet for review — see ResultPolicy.
 */
class ResultEntryController extends Controller
{
    public function __construct(
        private readonly GradingService $grading,
        private readonly ResultWorkflowService $workflow,
        private readonly ScoreSheetTemplate $template,
    ) {}

    /**
     * The lecturer's courses, split into those they lead and those they
     * co-teach.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $courses = $user->taughtCourses()
            ->with('department')
            ->withCount('registrations')
            ->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'course_code' => $course->course_code,
                'course_title' => $course->course_title,
                'credit_units' => $course->credit_units,
                'department' => $course->department->dept_name,
                'is_head' => $course->isHeadedBy($user),
                'registrations_count' => $course->registrations_count,
            ]);

        return Inertia::render('lecturer/courses', [
            'leading' => $courses->where('is_head', true)->values()->all(),
            'coTeaching' => $courses->where('is_head', false)->values()->all(),
            'currentSession' => config('grading.current_session'),
        ]);
    }

    /**
     * The score sheet for one course, session and semester.
     */
    public function show(Request $request, Course $course): Response
    {
        $this->authorize('enterScores', $course);

        $session = (string) $request->query('session', config('grading.current_session'));
        $semester = (int) $request->query('semester', 1);

        $registrations = Registration::query()
            ->with(['student.user', 'result.audits.user'])
            ->where('course_id', $course->getKey())
            ->where('session', $session)
            ->where('semester', $semester)
            ->get()
            ->sortBy(fn (Registration $registration) => $registration->student->reg_number)
            ->values();

        $course->load('lecturers', 'head');

        return Inertia::render('lecturer/score-sheet', [
            'course' => [
                'id' => $course->id,
                'course_code' => $course->course_code,
                'course_title' => $course->course_title,
                'credit_units' => $course->credit_units,
            ],
            'team' => $course->lecturers->map(fn ($lecturer) => [
                'id' => $lecturer->id,
                'name' => $lecturer->name,
                'is_head' => $lecturer->id === $course->lecturer_id,
            ])->values(),
            'session' => $session,
            'semester' => $semester,
            'sessions' => $this->availableSessions($course),
            'isHead' => $course->isHeadedBy($request->user()),
            'rows' => $registrations->map(fn (Registration $registration) => [
                'registration_id' => $registration->id,
                'reg_number' => $registration->student->reg_number,
                'student_name' => $registration->student->user->name,
                'ca_score' => $registration->result?->ca_score,
                'exam_score' => $registration->result?->exam_score,
                'total_score' => $registration->result?->total_score,
                'grade' => $registration->result?->grade,
                'status' => $registration->result?->status->value ?? ResultStatus::Draft->value,
                'editable' => $registration->result === null || $registration->result->allowsScoreEditing(),
                'last_edited_by' => $registration->result?->audits->first()?->user?->name,
                'edited_after_submission' => $this->wasEditedAfterSubmission($registration->result),
            ])->values(),
            'maxScores' => [
                'ca' => Grading::MAX_CA_SCORE,
                'exam' => Grading::MAX_EXAM_SCORE,
            ],
        ]);
    }

    /**
     * Save a batch of typed scores. Each row is authorised individually, so a
     * lecturer cannot slip in a row for a course they do not teach.
     */
    public function store(StoreScoresRequest $request, Course $course): RedirectResponse
    {
        $this->authorize('enterScores', $course);

        $saved = DB::transaction(function () use ($request, $course) {
            $count = 0;

            foreach ($request->validated('scores') as $row) {
                $registration = Registration::query()
                    ->where('course_id', $course->getKey())
                    ->findOrFail($row['registration_id']);

                $result = Result::query()->firstOrNew(['registration_id' => $registration->getKey()]);

                if ($result->exists) {
                    $this->authorize('update', $result);
                }

                $this->grading->record(
                    $registration,
                    (float) $row['ca_score'],
                    (float) $row['exam_score'],
                    $request->user(),
                );

                $count++;
            }

            return $count;
        });

        return back()->with('success', "Saved scores for {$saved} student(s).");
    }

    /**
     * Send the sheet for review. The head's action alone.
     */
    public function submit(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('submitScores', $course);

        $validated = $request->validate([
            'session' => ['required', 'string'],
            'semester' => ['required', 'integer', 'in:1,2'],
        ]);

        $results = $this->sheetResults($course, $validated['session'], (int) $validated['semester'], ResultStatus::Draft);

        if ($results->isEmpty()) {
            return back()->with('error', 'There are no draft results on this sheet to submit.');
        }

        try {
            $count = $this->workflow->submit($results, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Submitted {$count} result(s) for review.");
    }

    /**
     * Download the pre-filled .xlsx template for this sheet.
     */
    public function template(Request $request, Course $course): BinaryFileResponse
    {
        $this->authorize('enterScores', $course);

        $session = (string) $request->query('session', config('grading.current_session'));
        $semester = (int) $request->query('semester', 1);

        return response()
            ->download(
                $this->template->build($course, $session, $semester),
                $this->template->filename($course, $session, $semester),
            )
            ->deleteFileAfterSend();
    }

    /**
     * Sessions this course actually has registrations for.
     *
     * @return list<array{session: string, semester: int}>
     */
    private function availableSessions(Course $course): array
    {
        return Registration::query()
            ->where('course_id', $course->getKey())
            ->select('session', 'semester')
            ->distinct()
            ->orderBy('session')
            ->orderBy('semester')
            ->get()
            ->map(fn (Registration $registration) => [
                'session' => $registration->session,
                'semester' => $registration->semester,
            ])
            ->all();
    }

    /**
     * @return Collection<int, Result>
     */
    private function sheetResults(Course $course, string $session, int $semester, ResultStatus $status)
    {
        return Result::query()
            ->where('status', $status)
            ->whereHas('registration', fn ($query) => $query
                ->where('course_id', $course->getKey())
                ->where('session', $session)
                ->where('semester', $semester))
            ->get();
    }

    /**
     * Whether a submitted result has been edited since it was submitted.
     *
     * A lecturer keeps write access while a sheet is submitted, so the
     * examination officer needs to see when a row changed under them before
     * they approve it.
     */
    private function wasEditedAfterSubmission(?Result $result): bool
    {
        if ($result === null || $result->status !== ResultStatus::Submitted) {
            return false;
        }

        $submittedAt = $result->audits->firstWhere('action', 'submitted')?->created_at;

        if ($submittedAt === null) {
            return false;
        }

        return $result->audits
            ->where('action', 'scores_updated')
            ->contains(fn ($audit) => $audit->created_at->greaterThan($submittedAt));
    }
}
