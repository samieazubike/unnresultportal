<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Registration;
use App\Models\User;
use App\Support\Grading;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Throwable;

/**
 * Excel score upload (README §10).
 *
 * The rule that matters: validation must **reject and report, not silently
 * skip**. Every problem row is collected with its row number and reason, the
 * lecturer sees the whole list before anything is written, and the import
 * runs inside a transaction so a partial sheet never lands.
 *
 * Grade and grade point are computed on save, never read from the file, even
 * if the file contains a column for them.
 */
class ExcelResultImporter
{
    /** The columns the template requires, lower-cased. */
    private const REQUIRED_COLUMNS = ['reg_number', 'ca_score', 'exam_score'];

    public function __construct(private readonly GradingService $grading) {}

    /**
     * Parse and validate a sheet without writing anything.
     *
     * @return array{rows: list<array{row: int, reg_number: string, ca_score: float, exam_score: float, student_name: string}>,
     *               errors: list<array{row: int, reg_number: string, message: string}>}
     */
    public function preview(string $path, Course $course, string $session, int $semester): array
    {
        $table = $this->readSheet($path);
        $header = $this->validateHeader($table);

        // Registrations for this sheet, keyed by registration number: the
        // only students whose scores may legitimately appear in this file.
        $registrations = Registration::query()
            ->with(['student', 'result'])
            ->where('course_id', $course->getKey())
            ->where('session', $session)
            ->where('semester', $semester)
            ->get()
            ->keyBy(fn (Registration $registration) => $this->normalise($registration->student->reg_number));

        $rows = [];
        $errors = [];
        $seen = [];

        foreach (array_slice($table, 1) as $index => $raw) {
            // +2: one for the header row, one because spreadsheets are 1-indexed.
            $rowNumber = $index + 2;

            $regNumber = trim((string) ($raw[$header['reg_number']] ?? ''));
            $caRaw = $raw[$header['ca_score']] ?? null;
            $examRaw = $raw[$header['exam_score']] ?? null;

            if ($regNumber === '' && $this->isBlank($caRaw) && $this->isBlank($examRaw)) {
                continue; // Genuinely empty trailing row.
            }

            $key = $this->normalise($regNumber);

            $problem = match (true) {
                $regNumber === '' => 'Registration number is missing.',
                ! $registrations->has($key) => "No student with registration number {$regNumber} is registered for {$course->course_code} in {$session} semester {$semester}.",
                $registrations->get($key)->result?->allowsScoreEditing() === false => "Scores for {$regNumber} are locked (already approved) and cannot be changed.",
                isset($seen[$key]) => "Duplicate row: {$regNumber} already appears on row {$seen[$key]}.",
                $this->isBlank($caRaw) => 'CA score is missing.',
                $this->isBlank($examRaw) => 'Exam score is missing.',
                ! is_numeric($caRaw) => 'CA score must be a number.',
                ! is_numeric($examRaw) => 'Exam score must be a number.',
                (float) $caRaw < 0 || (float) $caRaw > Grading::MAX_CA_SCORE => 'CA score must be between 0 and '.Grading::MAX_CA_SCORE.'.',
                (float) $examRaw < 0 || (float) $examRaw > Grading::MAX_EXAM_SCORE => 'Exam score must be between 0 and '.Grading::MAX_EXAM_SCORE.'.',
                default => null,
            };

            if ($problem !== null) {
                $errors[] = ['row' => $rowNumber, 'reg_number' => $regNumber, 'message' => $problem];

                continue;
            }

            $seen[$key] = $rowNumber;
            $registration = $registrations->get($key);

            $rows[] = [
                'row' => $rowNumber,
                'registration_id' => $registration->getKey(),
                'reg_number' => $registration->student->reg_number,
                'student_name' => $registration->student->name,
                'ca_score' => (float) $caRaw,
                'exam_score' => (float) $examRaw,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Validate and, only if the sheet is clean, write it.
     *
     * Nothing is saved while any row is in error — §10 requires the lecturer
     * to see the full list first.
     *
     * The lecturer's upload no longer goes through here: it stages the parsed
     * rows into the score sheet's inputs via {@see preview()} and writes only
     * when the lecturer saves. This remains the transactional writer for any
     * caller that wants a sheet applied in one step.
     *
     * @return array{imported: int, errors: list<array{row: int, reg_number: string, message: string}>}
     */
    public function import(string $path, Course $course, string $session, int $semester, User $lecturer): array
    {
        $preview = $this->preview($path, $course, $session, $semester);

        if ($preview['errors'] !== []) {
            return ['imported' => 0, 'errors' => $preview['errors']];
        }

        $imported = DB::transaction(function () use ($preview, $lecturer) {
            $count = 0;

            foreach ($preview['rows'] as $row) {
                $registration = Registration::query()->findOrFail($row['registration_id']);

                $this->grading->record($registration, $row['ca_score'], $row['exam_score'], $lecturer);
                $count++;
            }

            return $count;
        });

        return ['imported' => $imported, 'errors' => []];
    }

    /**
     * @return list<list<mixed>>
     */
    private function readSheet(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (ReaderException|Throwable $exception) {
            throw new ImportFailed(
                'The file could not be read as a spreadsheet. Save it as .xlsx and try again.',
                previous: $exception,
            );
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if ($rows === []) {
            throw new ImportFailed('The spreadsheet is empty.');
        }

        return $rows;
    }

    /**
     * Map the required column names to their positions, rejecting a sheet
     * that is missing any of them.
     *
     * @param  list<list<mixed>>  $table
     * @return array<string, int>
     */
    private function validateHeader(array $table): array
    {
        $header = [];

        foreach ($table[0] as $position => $label) {
            $normalised = str_replace([' ', '-'], '_', strtolower(trim((string) $label)));

            if ($normalised !== '') {
                $header[$normalised] = $position;
            }
        }

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, array_keys($header)));

        if ($missing !== []) {
            throw new ImportFailed(
                'The spreadsheet is missing required column(s): '.implode(', ', $missing)
                .'. Download the template to see the expected layout.'
            );
        }

        return $header;
    }

    /**
     * Registration numbers are compared case- and whitespace-insensitively so
     * a stray space in the file is not reported as an unknown student.
     */
    private function normalise(string $regNumber): string
    {
        return strtolower(preg_replace('/\s+/', '', $regNumber) ?? $regNumber);
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
