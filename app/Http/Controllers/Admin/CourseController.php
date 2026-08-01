<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCourseRequest;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Course records and lecturer assignment (README §3, §14.3).
 *
 * Assignment is the administrator's, and it is strictly assignment: nothing
 * in this controller can touch a score.
 */
class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('manage', Course::class);

        $courses = Course::query()
            ->with(['department', 'head', 'lecturers'])
            ->orderBy('course_code')
            ->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'course_code' => $course->course_code,
                'course_title' => $course->course_title,
                'credit_units' => $course->credit_units,
                'department' => $course->department->dept_name,
                'dept_id' => $course->dept_id,
                'head' => $course->head?->only(['id', 'name']),
                'lecturers' => $course->lecturers->map->only(['id', 'name'])->values(),
            ]);

        return Inertia::render('admin/courses', [
            'courses' => $courses,
            'departments' => Department::query()->orderBy('dept_name')->get(['id', 'dept_name']),
            'lecturers' => User::query()
                ->where('role', UserRole::Lecturer)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function store(SaveCourseRequest $request): RedirectResponse
    {
        $this->authorize('create', Course::class);

        $this->save(new Course, $request->validated());

        return back()->with('success', 'Course created.');
    }

    public function update(SaveCourseRequest $request, Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        $this->save($course, $request->validated());

        return back()->with('success', 'Course updated.');
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('delete', $course);

        DB::transaction(function () use ($course) {
            // Clear the head first: the composite foreign key will not let a
            // course keep a head whose pivot row is going away.
            $course->forceFill(['lecturer_id' => null])->save();
            $course->lecturers()->detach();
            $course->delete();
        });

        return back()->with('success', 'Course deleted.');
    }

    /**
     * Persist a course together with its teaching team.
     *
     * Both writes happen inside one transaction so the deferred composite
     * foreign key can check "the head is a team member" at commit rather than
     * forcing a particular insert order.
     *
     * @param  array<string, mixed>  $data
     */
    private function save(Course $course, array $data): void
    {
        DB::transaction(function () use ($course, $data) {
            $course->fill([
                'course_code' => $data['course_code'],
                'course_title' => $data['course_title'],
                'credit_units' => $data['credit_units'],
                'dept_id' => $data['dept_id'],
            ]);

            // Detach the head while the team is being resynced, then set it.
            $course->lecturer_id = null;
            $course->save();

            $course->lecturers()->sync($data['lecturer_ids'] ?? []);

            $course->forceFill(['lecturer_id' => $data['head_id'] ?? null])->save();
        });
    }
}
