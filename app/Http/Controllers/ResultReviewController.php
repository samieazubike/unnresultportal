<?php

namespace App\Http\Controllers;

use App\Enums\ResultStatus;
use App\Models\Result;
use App\Services\ResultWorkflowService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Approve and reject (README §9, §14.7).
 *
 * Note what these actions accept: a sheet identifier and, for a rejection, a
 * note. No score field appears in any validation rule here, so approval is
 * structurally incapable of altering a mark.
 */
class ResultReviewController extends Controller
{
    public function __construct(private readonly ResultWorkflowService $workflow) {}

    /**
     * Sheets awaiting review, grouped by course, session and semester.
     */
    public function index(Request $request): Response
    {
        $sheets = Result::query()
            ->with(['registration.course.head', 'audits'])
            ->where('status', ResultStatus::Submitted)
            ->get()
            ->groupBy(fn (Result $result) => implode('|', [
                $result->registration->course_id,
                $result->registration->session,
                $result->registration->semester,
            ]))
            ->map(function ($group) {
                /** @var Result $first */
                $first = $group->first();
                $course = $first->registration->course;

                return [
                    'key' => implode('|', [$course->id, $first->registration->session, $first->registration->semester]),
                    'course_id' => $course->id,
                    'course_code' => $course->course_code,
                    'course_title' => $course->course_title,
                    'head' => $course->head?->name,
                    'session' => $first->registration->session,
                    'semester' => $first->registration->semester,
                    'results' => $group->count(),
                    'failures' => $group->where('grade', 'F')->count(),
                    'edited_after_submission' => $group->contains(
                        fn (Result $result) => $this->wasEditedAfterSubmission($result)
                    ),
                    'url' => route('review.show', [
                        $course->id,
                        str_replace('/', '-', $first->registration->session),
                        $first->registration->semester,
                    ]),
                ];
            })
            ->values();

        return Inertia::render('exam-officer/review', [
            'sheets' => $sheets,
        ]);
    }

    /**
     * The individual results on one submitted sheet, for inspection before a
     * decision.
     */
    public function show(Request $request, int $courseId, string $session, int $semester): Response
    {
        // Sessions are stored as 2025/2026 but travel in the URL as 2025-2026.
        $session = str_replace('-', '/', $session);

        $results = $this->sheet($courseId, $session, $semester, ResultStatus::Submitted);

        abort_if($results->isEmpty(), 404);

        $this->authorize('approve', $results->first());

        $course = $results->first()->registration->course;

        return Inertia::render('exam-officer/sheet', [
            'course' => [
                'id' => $course->id,
                'course_code' => $course->course_code,
                'course_title' => $course->course_title,
                'head' => $course->head?->name,
            ],
            'session' => $session,
            'semester' => $semester,
            'rows' => $results->map(fn (Result $result) => [
                'id' => $result->id,
                'reg_number' => $result->registration->student->reg_number,
                'student_name' => $result->registration->student->name,
                'ca_score' => $result->ca_score,
                'exam_score' => $result->exam_score,
                'total_score' => $result->total_score,
                'grade' => $result->grade,
                'edited_after_submission' => $this->wasEditedAfterSubmission($result),
            ])->values(),
        ]);
    }

    public function approve(Request $request): RedirectResponse
    {
        $validated = $this->validateSheet($request);

        $results = $this->sheet(
            (int) $validated['course_id'],
            $validated['session'],
            (int) $validated['semester'],
            ResultStatus::Submitted,
        );

        if ($results->isEmpty()) {
            return back()->with('error', 'There is nothing on this sheet awaiting approval.');
        }

        $this->authorize('approve', $results->first());

        try {
            $count = $this->workflow->approve($results, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('review.index')->with(
            'success',
            "Approved {$count} result(s). They are not visible to students until they are published."
        );
    }

    public function reject(Request $request): RedirectResponse
    {
        $validated = $this->validateSheet($request, [
            'note' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $results = $this->sheet(
            (int) $validated['course_id'],
            $validated['session'],
            (int) $validated['semester'],
            ResultStatus::Submitted,
        );

        if ($results->isEmpty()) {
            return back()->with('error', 'There is nothing on this sheet awaiting review.');
        }

        $this->authorize('reject', $results->first());

        $count = $this->workflow->reject($results, $request->user(), $validated['note']);

        return to_route('review.index')->with('success', "Returned {$count} result(s) to the lecturer.");
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function validateSheet(Request $request, array $extra = []): array
    {
        // Deliberately no score fields. Approval and rejection change status
        // and nothing else.
        return $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'session' => ['required', 'string'],
            'semester' => ['required', 'integer', 'in:1,2'],
            ...$extra,
        ]);
    }

    /**
     * @return Collection<int, Result>
     */
    private function sheet(int $courseId, string $session, int $semester, ResultStatus $status)
    {
        return Result::query()
            ->with(['registration.student', 'registration.course.head', 'audits'])
            ->where('status', $status)
            ->whereHas('registration', fn ($query) => $query
                ->where('course_id', $courseId)
                ->where('session', $session)
                ->where('semester', $semester))
            ->get();
    }

    /**
     * A lecturer keeps write access while a sheet is submitted, so flag any
     * row that changed after it was sent for review — the officer should
     * approve with that in view.
     */
    private function wasEditedAfterSubmission(Result $result): bool
    {
        $submittedAt = $result->audits->firstWhere('action', 'submitted')?->created_at;

        if ($submittedAt === null) {
            return false;
        }

        return $result->audits
            ->where('action', 'scores_updated')
            ->contains(fn ($audit) => $audit->created_at->greaterThan($submittedAt));
    }
}
