<?php

namespace App\Http\Controllers;

use App\Enums\ResultStatus;
use App\Models\Result;
use App\Services\ResultIntegrityService;
use App\Services\ResultWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Publication (README §9, §14.8).
 *
 * A separate deliberate action from approval — §16 names collapsing the two
 * as breaking the defence. Approved results wait here until an examination
 * officer or the ICT director releases them, and who did it is recorded in
 * results.published_by.
 *
 * This controller accepts no score field of any kind. It also refuses to
 * release any result whose integrity signature no longer matches, which is
 * the point in the system where the widest authority is exercised.
 */
class ResultPublicationController extends Controller
{
    public function __construct(
        private readonly ResultWorkflowService $workflow,
        private readonly ResultIntegrityService $integrity,
    ) {}

    /**
     * Approved sheets awaiting release.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->canPublishResults(), 403);

        $approved = Result::query()
            ->with(['registration.course', 'signature'])
            ->where('status', ResultStatus::Approved)
            ->get();

        $tamperedIds = $this->integrity->tampered($approved)->pluck('id')->all();

        $sheets = $approved
            ->groupBy(fn (Result $result) => implode('|', [
                $result->registration->course_id,
                $result->registration->session,
                $result->registration->semester,
            ]))
            ->map(function ($group) use ($tamperedIds) {
                /** @var Result $first */
                $first = $group->first();
                $course = $first->registration->course;

                return [
                    'course_id' => $course->id,
                    'course_code' => $course->course_code,
                    'course_title' => $course->course_title,
                    'session' => $first->registration->session,
                    'semester' => $first->registration->semester,
                    'results' => $group->count(),
                    'failures' => $group->where('grade', 'F')->count(),
                    'integrity_failures' => $group->whereIn('id', $tamperedIds)->count(),
                ];
            })
            ->values();

        return Inertia::render('exam-officer/publish', [
            'sheets' => $sheets,
            'integrityFailures' => count($tamperedIds),
        ]);
    }

    /**
     * Release a sheet to students.
     */
    public function store(Request $request): RedirectResponse
    {
        // No score field is accepted. Publication moves status and records
        // who did it; it cannot carry a mark even if one were posted.
        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'session' => ['required', 'string'],
            'semester' => ['required', 'integer', 'in:1,2'],
        ]);

        $results = Result::query()
            ->with('signature')
            ->where('status', ResultStatus::Approved)
            ->whereHas('registration', fn ($query) => $query
                ->where('course_id', $validated['course_id'])
                ->where('session', $validated['session'])
                ->where('semester', $validated['semester']))
            ->get();

        if ($results->isEmpty()) {
            return back()->with('error', 'There are no approved results on this sheet to publish.');
        }

        $this->authorize('publish', $results->first());

        try {
            $outcome = $this->workflow->publish($results, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($outcome['rejected'] !== []) {
            return back()
                ->with('error', sprintf(
                    'Published %d result(s). %d were held back because their integrity signature did not match — '
                    .'those records were altered outside the application and need investigation.',
                    $outcome['published'],
                    count($outcome['rejected']),
                ))
                ->with('integrityFailures', $outcome['rejected']);
        }

        return back()->with('success', "Published {$outcome['published']} result(s). Students can now see them.");
    }

    /**
     * The integrity screen: every result whose stored signature no longer
     * matches its contents.
     */
    public function integrity(Request $request): Response
    {
        abort_unless($request->user()->canPublishResults(), 403);

        $signed = Result::query()
            ->with(['registration.course', 'registration.student.user', 'signature', 'audits.user'])
            ->whereHas('signature')
            ->get();

        $tampered = $this->integrity->tampered($signed);

        return Inertia::render('exam-officer/integrity', [
            'results' => $tampered->map(fn (Result $result) => [
                'id' => $result->id,
                'course_code' => $result->registration->course->course_code,
                'reg_number' => $result->registration->student->reg_number,
                'student_name' => $result->registration->student->user->name,
                'session' => $result->registration->session,
                'semester' => $result->registration->semester,
                'status' => $result->status->value,
                'current_grade' => $result->grade,
                'current_total' => $result->total_score,
                'signed_at' => $result->signature?->signed_at?->toIso8601String(),
                'signed_by' => $result->signature?->signer?->name,
                'last_audit' => $result->audits->first()?->only(['action', 'created_at']),
            ])->values(),
            'checked' => $signed->count(),
        ]);
    }
}
