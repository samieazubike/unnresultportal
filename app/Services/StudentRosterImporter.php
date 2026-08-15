<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Throwable;

/**
 * Bulk entry of the student roster.
 *
 * Follows the same rule as the score importer (README §10): validation must
 * reject and report, not silently skip. Every problem row is collected with
 * its row number and reason, the administrator sees the whole list before
 * anything is written, and the import runs inside a transaction so half a
 * cohort never lands.
 *
 * Department and entry year come from the upload form rather than the sheet:
 * an administrator loads one cohort at a time, and matching department names
 * out of a spreadsheet by hand would be exactly the kind of guessing that
 * silently attaches a student to the wrong department. A per-row `entry_year`
 * column is honoured when present, for the cohorts that are mixed.
 */
class StudentRosterImporter
{
    /** The columns the template requires, lower-cased. */
    private const REQUIRED_COLUMNS = ['reg_number', 'name'];

    private const EARLIEST_ENTRY_YEAR = 1960;

    /**
     * Parse and validate a sheet without writing anything.
     *
     * @return array{rows: list<array{row: int, reg_number: string, name: string, dept_id: int, entry_year: int}>,
     *               errors: list<array{row: int, reg_number: string, message: string}>}
     */
    public function preview(string $path, Department $department, int $entryYear): array
    {
        $table = $this->readSheet($path);
        $header = $this->validateHeader($table);

        $rows = [];
        $errors = [];
        $seen = [];

        foreach (array_slice($table, 1) as $index => $raw) {
            // +2: one for the header row, one because spreadsheets are 1-indexed.
            $rowNumber = $index + 2;

            $regNumber = trim((string) ($raw[$header['reg_number']] ?? ''));
            $name = trim((string) ($raw[$header['name']] ?? ''));
            $yearRaw = isset($header['entry_year']) ? ($raw[$header['entry_year']] ?? null) : null;

            if ($regNumber === '' && $name === '' && $this->isBlank($yearRaw)) {
                continue; // Genuinely empty trailing row.
            }

            $key = $this->normalise($regNumber);

            $problem = match (true) {
                $regNumber === '' => 'Registration number is missing.',
                $name === '' => 'Name is missing.',
                isset($seen[$key]) => "Duplicate row: {$regNumber} already appears on row {$seen[$key]}.",
                $this->alreadyOnRoster($regNumber) => "{$regNumber} is already on the roster.",
                ! $this->isBlank($yearRaw) && ! is_numeric($yearRaw) => 'Entry year must be a number.',
                ! $this->isBlank($yearRaw) && ! $this->isPlausibleYear((int) $yearRaw) => 'Entry year must be between '.self::EARLIEST_ENTRY_YEAR.' and '.$this->latestEntryYear().'.',
                default => null,
            };

            if ($problem !== null) {
                $errors[] = ['row' => $rowNumber, 'reg_number' => $regNumber, 'message' => $problem];

                continue;
            }

            $seen[$key] = $rowNumber;

            $rows[] = [
                'row' => $rowNumber,
                'reg_number' => $regNumber,
                'name' => $name,
                'dept_id' => $department->getKey(),
                'entry_year' => $this->isBlank($yearRaw) ? $entryYear : (int) $yearRaw,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Validate and, only if the sheet is clean, write it.
     *
     * @return array{imported: int, errors: list<array{row: int, reg_number: string, message: string}>}
     */
    public function import(string $path, Department $department, int $entryYear): array
    {
        $preview = $this->preview($path, $department, $entryYear);

        if ($preview['errors'] !== []) {
            return ['imported' => 0, 'errors' => $preview['errors']];
        }

        $imported = DB::transaction(function () use ($preview): int {
            $count = 0;

            foreach ($preview['rows'] as $row) {
                Student::query()->create([
                    'name' => $row['name'],
                    'reg_number' => $row['reg_number'],
                    'dept_id' => $row['dept_id'],
                    'entry_year' => $row['entry_year'],
                ]);

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

    private function alreadyOnRoster(string $regNumber): bool
    {
        return Student::query()->matchingRegNumber($regNumber)->exists();
    }

    private function isPlausibleYear(int $year): bool
    {
        return $year >= self::EARLIEST_ENTRY_YEAR && $year <= $this->latestEntryYear();
    }

    private function latestEntryYear(): int
    {
        return (int) date('Y') + 1;
    }

    /**
     * Registration numbers are compared case- and whitespace-insensitively so
     * a stray space in the file is not read as a different student.
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
