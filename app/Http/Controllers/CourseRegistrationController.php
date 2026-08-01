<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Registration;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Student course registration (README §14.4).
 *
 * The UNIQUE(student_id, course_id, session, semester) constraint means a
 * retake of the same course in a later session is a distinct registration
 * carrying its own result, exactly as §4 intends.
 */
class CourseRegistrationController extends Controller
{
    public function index(Request $request): Response
    {
        $student = Student::query()
            ->with('department')
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();

        $session = (string) $request->query('session', config('grading.current_session'));
        $semester = (int) $request->query('semester', 1);

        $registered = Registration::query()
            ->with(['course', 'result'])
            ->where('student_id', $student->getKey())
            ->where('session', $session)
            ->where('semester', $semester)
            ->get();

        return Inertia::render('student/registration', [
            'student' => [
                'reg_number' => $student->reg_number,
                'department' => $student->department->dept_name,
            ],
            'session' => $session,
            'semester' => $semester,
            'registered' => $registered->map(fn (Registration $registration) => [
                'id' => $registration->id,
                'course_id' => $registration->course_id,
                'course_code' => $registration->course->course_code,
                'course_title' => $registration->course->course_title,
                'credit_units' => $registration->course->credit_units,
                'has_result' => $registration->result !== null,
            ])->values(),
            'available' => Course::query()
                ->where('dept_id', $student->dept_id)
                ->whereNotIn('id', $registered->pluck('course_id'))
                ->orderBy('course_code')
                ->get(['id', 'course_code', 'course_title', 'credit_units']),
            'totalCreditUnits' => $registered->sum(fn (Registration $r) => $r->course->credit_units),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = Student::query()->where('user_id', $request->user()->getKey())->firstOrFail();

        $validated = $request->validate([
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'session' => ['required', 'string'],
            'semester' => ['required', 'integer', 'in:1,2'],
        ]);

        $added = DB::transaction(function () use ($student, $validated) {
            $count = 0;

            foreach ($validated['course_ids'] as $courseId) {
                $registration = Registration::query()->firstOrCreate([
                    'student_id' => $student->getKey(),
                    'course_id' => $courseId,
                    'session' => $validated['session'],
                    'semester' => $validated['semester'],
                ]);

                if ($registration->wasRecentlyCreated) {
                    $count++;
                }
            }

            return $count;
        });

        return back()->with('success', "Registered for {$added} course(s).");
    }

    /**
     * Drop a registration, provided no result has been recorded against it.
     */
    public function destroy(Request $request, Registration $registration): RedirectResponse
    {
        $student = Student::query()->where('user_id', $request->user()->getKey())->firstOrFail();

        abort_unless($registration->student_id === $student->getKey(), 403);

        if ($registration->result()->exists()) {
            return back()->with('error', 'This course already has a result recorded and cannot be dropped.');
        }

        $registration->delete();

        return back()->with('success', 'Course dropped.');
    }
}
