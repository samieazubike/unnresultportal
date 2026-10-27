<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationSource;
use App\Models\Course;
use App\Models\Registration;
use App\Models\RegistrationWindow;
use App\Models\Student;
use App\Support\AcademicLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Student course registration (README §14.4).
 *
 * An administrator pre-selects the courses for each department and level, and
 * those registrations already exist by the time the student arrives (see
 * CourseOfferingService). While the administrator holds the registration
 * window open, the student may drop any course without a result and add
 * their carryovers or other courses from their department.
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

        $current = RegistrationWindow::current();

        $session = (string) $request->query('session', $current->session ?? config('grading.current_session'));
        $semester = (int) $request->query('semester', $current->semester ?? 1);

        $registered = Registration::query()
            ->with(['course', 'result'])
            ->where('student_id', $student->getKey())
            ->where('session', $session)
            ->where('semester', $semester)
            ->get();

        $carryoverIds = $student->carryoverCourseIds();
        $registeredIds = $registered->pluck('course_id')->all();

        $courseFields = ['id', 'course_code', 'course_title', 'credit_units'];

        return Inertia::render('student/registration', [
            'student' => [
                'reg_number' => $student->reg_number,
                'department' => $student->department->dept_name,
                'level' => AcademicLevel::forStudent($student, $session),
            ],
            'session' => $session,
            'semester' => $semester,
            'isOpen' => RegistrationWindow::isOpen($session, $semester),
            'registered' => $registered
                ->sortBy(fn (Registration $registration) => $registration->course->course_code)
                ->map(fn (Registration $registration) => [
                    'id' => $registration->id,
                    'course_id' => $registration->course_id,
                    'course_code' => $registration->course->course_code,
                    'course_title' => $registration->course->course_title,
                    'credit_units' => $registration->course->credit_units,
                    'has_result' => $registration->result !== null,
                    'source' => $registration->source->value,
                    'is_carryover' => in_array($registration->course_id, $carryoverIds, strict: true),
                ])
                ->values(),
            'carryovers' => Course::query()
                ->whereIn('id', array_diff($carryoverIds, $registeredIds))
                ->orderBy('course_code')
                ->get($courseFields),
            'available' => Course::query()
                ->where('dept_id', $student->dept_id)
                ->whereNotIn('id', [...$registeredIds, ...$carryoverIds])
                ->orderBy('course_code')
                ->get($courseFields),
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

        if (! RegistrationWindow::isOpen($validated['session'], (int) $validated['semester'])) {
            return back()->with('error', 'Course registration is closed for this semester.');
        }

        // A student may add their own carryovers, or any course their
        // department runs. Anything else is outside what they can register.
        $permitted = Course::query()
            ->whereIn('id', $validated['course_ids'])
            ->where(fn ($query) => $query
                ->where('dept_id', $student->dept_id)
                ->orWhereIn('id', $student->carryoverCourseIds()))
            ->pluck('id');

        if ($permitted->count() !== count(array_unique($validated['course_ids']))) {
            return back()->with('error', 'You can only add carryover courses or courses offered by your department.');
        }

        $added = DB::transaction(function () use ($student, $validated, $permitted) {
            $count = 0;

            foreach ($permitted as $courseId) {
                $registration = Registration::query()->firstOrCreate([
                    'student_id' => $student->getKey(),
                    'course_id' => $courseId,
                    'session' => $validated['session'],
                    'semester' => $validated['semester'],
                ], [
                    'source' => RegistrationSource::Student,
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
     * Drop a registration while the window is open, provided no result has
     * been recorded against it.
     */
    public function destroy(Request $request, Registration $registration): RedirectResponse
    {
        $student = Student::query()->where('user_id', $request->user()->getKey())->firstOrFail();

        abort_unless($registration->student_id === $student->getKey(), 403);

        if (! RegistrationWindow::isOpen($registration->session, $registration->semester)) {
            return back()->with('error', 'Course registration is closed for this semester.');
        }

        if ($registration->result()->exists()) {
            return back()->with('error', 'This course already has a result recorded and cannot be dropped.');
        }

        $registration->delete();

        return back()->with('success', 'Course dropped.');
    }
}
