<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Registration;
use App\Support\Grading;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the .xlsx a lecturer downloads, pre-filled with the class list for
 * the session and semester so the registration numbers always match what the
 * importer expects.
 */
class ScoreSheetTemplate
{
    public function build(Course $course, string $session, int $semester): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Scores');

        $sheet->fromArray([
            ['reg_number', 'student_name', 'ca_score', 'exam_score'],
        ], null, 'A1');

        $sheet->getStyle('A1:D1')->getFont()->setBold(true);

        $registrations = Registration::query()
            ->with('student')
            ->where('course_id', $course->getKey())
            ->where('session', $session)
            ->where('semester', $semester)
            ->get()
            ->sortBy(fn (Registration $registration) => $registration->student->reg_number);

        $row = 2;

        foreach ($registrations as $registration) {
            $sheet->fromArray([[
                $registration->student->reg_number,
                $registration->student->name,
                $registration->result?->ca_score,
                $registration->result?->exam_score,
            ]], null, "A{$row}");

            $row++;
        }

        // student_name is informational only; the importer keys on reg_number.
        $sheet->getComment('B1')->getText()->createTextRun('For reference only. Scores are matched by registration number.');

        $sheet->getComment('C1')->getText()->createTextRun('Out of '.Grading::MAX_CA_SCORE.'.');
        $sheet->getComment('D1')->getText()->createTextRun('Out of '.Grading::MAX_EXAM_SCORE.'.');

        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'scoresheet').'.xlsx';

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function filename(Course $course, string $session, int $semester): string
    {
        return sprintf(
            '%s-%s-semester-%d-scores.xlsx',
            str_replace(' ', '', $course->course_code),
            str_replace('/', '-', $session),
            $semester,
        );
    }
}
