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
 *
 * The upload writes nothing. It parses the sheet and flashes the rows back so
 * they land in the score sheet's inputs as unsaved edits, where the lecturer
 * can correct them before saving. Rows that could not be read are flashed
 * beside them, so a single bad row no longer costs the lecturer the whole
 * sheet: the good rows are staged and the bad ones are typed in by hand. The
 * one writer is still ResultEntryController@store.
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
        ]);

        $path = $request->file('sheet')->store(self::DIRECTORY, self::DISK);

        try {
            $outcome = $this->importer->preview(
                Storage::disk(self::DISK)->path($path),
                $course,
                $validated['session'],
                (int) $validated['semester'],
            );
        } catch (ImportFailed $exception) {
            return back()->with('error', $exception->getMessage());
        } finally {
            Storage::disk(self::DISK)->delete($path);
        }

        $staged = array_map(fn (array $row) => [
            'registration_id' => $row['registration_id'],
            'reg_number' => $row['reg_number'],
            'ca_score' => $row['ca_score'],
            'exam_score' => $row['exam_score'],
        ], $outcome['rows']);

        if ($outcome['errors'] !== []) {
            // The readable rows still reach the inputs; the lecturer sees the
            // full row-by-row list for the rest (§10). Nothing is written
            // either way, so a bad row no longer costs the whole sheet.
            return back()
                ->with('error', count($outcome['errors']).' row(s) could not be read. Nothing has been saved yet.')
                ->with('importErrors', $outcome['errors'])
                ->with('importRows', $staged);
        }

        return back()
            ->with('success', 'Loaded scores for '.count($staged).' student(s). Review, then save.')
            ->with('importRows', $staged);
    }
}
