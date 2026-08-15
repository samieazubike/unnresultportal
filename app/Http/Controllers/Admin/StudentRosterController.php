<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRosterStudentRequest;
use App\Models\Course;
use App\Models\Department;
use App\Models\Student;
use App\Services\ImportFailed;
use App\Services\StudentRosterImporter;
use App\Services\StudentRosterTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The student roster: who exists, before anyone has an account.
 *
 * Every student in the portal starts here. Registration is a student claiming
 * one of these rows, so nothing a student can reach ever creates one.
 *
 * The upload carries no marks, but it is handled like the score upload
 * anyway: private disk, read once, deleted in a finally block.
 */
class StudentRosterController extends Controller
{
    private const DISK = 'local';

    private const DIRECTORY = 'private/imports';

    public function __construct(
        private readonly StudentRosterImporter $importer,
        private readonly StudentRosterTemplate $template,
    ) {}

    public function store(StoreRosterStudentRequest $request): RedirectResponse
    {
        $this->authorize('manage', Course::class);

        $student = Student::query()->create($request->validated());

        return back()->with('success', "{$student->name} added to the roster.");
    }

    public function template(): BinaryFileResponse
    {
        $this->authorize('manage', Course::class);

        return response()
            ->download($this->template->build(), $this->template->filename())
            ->deleteFileAfterSend();
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('manage', Course::class);

        $validated = $request->validate([
            'sheet' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            'dept_id' => ['required', 'integer', 'exists:departments,id'],
            'entry_year' => ['required', 'integer', 'min:1960', 'max:'.(date('Y') + 1)],
        ]);

        $department = Department::query()->findOrFail($validated['dept_id']);

        $path = $request->file('sheet')->store(self::DIRECTORY, self::DISK);

        try {
            $outcome = $this->importer->import(
                Storage::disk(self::DISK)->path($path),
                $department,
                (int) $validated['entry_year'],
            );
        } catch (ImportFailed $exception) {
            return back()->with('error', $exception->getMessage());
        } finally {
            Storage::disk(self::DISK)->delete($path);
        }

        if ($outcome['errors'] !== []) {
            // Nothing was saved. The administrator sees the full row-by-row
            // list before any part of the sheet lands.
            return back()
                ->with('error', count($outcome['errors']).' row(s) could not be imported. No students were added.')
                ->with('importErrors', $outcome['errors']);
        }

        return back()->with('success', "Added {$outcome['imported']} student(s) to the roster.");
    }
}
