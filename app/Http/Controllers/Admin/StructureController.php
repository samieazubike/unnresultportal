<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Faculties, departments, users and the failure-rate threshold — the whole of
 * the administrator's remit under README §3.
 *
 * Deliberately absent: anything that writes a score.
 */
class StructureController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('manage', Course::class);

        return Inertia::render('admin/structure', [
            'faculties' => Faculty::query()
                ->withCount('departments')
                ->orderBy('faculty_name')
                ->get(),
            'departments' => Department::query()
                ->with('faculty')
                ->withCount(['students', 'courses'])
                ->orderBy('dept_name')
                ->get()
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'dept_name' => $department->dept_name,
                    'faculty_id' => $department->faculty_id,
                    'faculty' => $department->faculty->faculty_name,
                    'students_count' => $department->students_count,
                    'courses_count' => $department->courses_count,
                ]),
            'failureThreshold' => Setting::failureThreshold(),
        ]);
    }

    public function storeFaculty(Request $request): RedirectResponse
    {
        $this->authorize('manage', Course::class);

        Faculty::query()->create($request->validate([
            'faculty_name' => ['required', 'string', 'max:255', 'unique:faculties,faculty_name'],
        ]));

        return back()->with('success', 'Faculty created.');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $this->authorize('manage', Course::class);

        Department::query()->create($request->validate([
            'dept_name' => ['required', 'string', 'max:255', 'unique:departments,dept_name'],
            'faculty_id' => ['required', 'integer', 'exists:faculties,id'],
        ]));

        return back()->with('success', 'Department created.');
    }

    /**
     * The admin-configurable failure-rate threshold (README §7).
     */
    public function updateThreshold(Request $request): RedirectResponse
    {
        $this->authorize('manage', Course::class);

        $validated = $request->validate([
            'failure_threshold' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        Setting::put(Setting::FAILURE_THRESHOLD, $validated['failure_threshold']);

        return back()->with('success', "Courses are now flagged above a {$validated['failure_threshold']}% failure rate.");
    }

    public function users(Request $request): Response
    {
        $this->authorize('manage', Course::class);

        return Inertia::render('admin/users', [
            'users' => User::query()
                ->with('student.department')
                ->orderBy('name')
                ->paginate(25)
                ->through(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'reg_number' => $user->student?->reg_number,
                    'department' => $user->student?->department?->dept_name,
                ]),
            'roles' => array_map(
                fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()],
                UserRole::cases(),
            ),
            'departments' => Department::query()->orderBy('dept_name')->get(['id', 'dept_name']),
        ]);
    }

    /**
     * Change a user's role. Note this grants or removes the ability to enter
     * scores, but never writes one.
     */
    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manage', Course::class);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $user->update(['role' => $validated['role']]);

        return back()->with('success', "{$user->name} is now a ".UserRole::from($validated['role'])->label().'.');
    }

    /**
     * Attach a student record to a user account.
     */
    public function storeStudent(Request $request): RedirectResponse
    {
        $this->authorize('manage', Course::class);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:students,user_id'],
            'reg_number' => ['required', 'string', 'max:20', 'unique:students,reg_number'],
            'dept_id' => ['required', 'integer', 'exists:departments,id'],
            'entry_year' => ['required', 'integer', 'min:1960', 'max:'.(date('Y') + 1)],
        ]);

        Student::query()->create($validated);
        User::query()->whereKey($validated['user_id'])->update(['role' => UserRole::Student]);

        return back()->with('success', 'Student record created.');
    }
}
