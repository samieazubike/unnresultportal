<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\ExcelResultImporter;
use App\Services\ImportFailed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Excel score upload (README §10, §14.6).
 *
 * The upload carries unreleased marks, so it is written to private storage
 * (never the public disk), read once, and deleted in a finally block whether
 * the import succeeded, failed validation, or threw. It is never encrypted at
 * rest: the spreadsheet reader needs a real file on disk, so encrypting it
 * only to decrypt it a line later would buy nothing. Keeping its lifetime to
 * a single request is the control that actually helps.
 */
class ResultUploadController extends Controller
{
    private const DISK = 'local';

    private const DIRECTORY = 'private/imports';

    public function __construct(private readonly ExcelResultImporter $importer) {}

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('enterScores', $course);

        $validated = $request->validate([
            'sheet' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            'session' => ['required', 'string'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'confirm' => ['sometimes', 'boolean'],
        ]);

        $path = $request->file('sheet')->store(self::DIRECTORY, self::DISK);

        try {
            $outcome = $this->importer->import(
                Storage::disk(self::DISK)->path($path),
                $course,
                $validated['session'],
                (int) $validated['semester'],
                $request->user(),
            );
        } catch (ImportFailed $exception) {
            return back()->with('error', $exception->getMessage());
        } finally {
            Storage::disk(self::DISK)->delete($path);
        }

        if ($outcome['errors'] !== []) {
            // Nothing was saved. The lecturer sees the full row-by-row list
            // before any part of the sheet lands (§10).
            return back()
                ->with('error', count($outcome['errors']).' row(s) could not be imported. No scores were saved.')
                ->with('importErrors', $outcome['errors']);
        }

        return back()->with('success', "Imported scores for {$outcome['imported']} student(s).");
    }
}
