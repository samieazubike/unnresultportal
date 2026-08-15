<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The .xlsx an administrator downloads before uploading a cohort.
 *
 * Two example rows rather than an empty grid: the registration number format
 * is the thing most often got wrong, and showing it costs nothing.
 */
class StudentRosterTemplate
{
    public function build(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Roster');

        $sheet->fromArray([
            ['reg_number', 'name', 'entry_year'],
            ['2021/000123', 'Ada Lovelace', null],
            ['2021/000124', 'Chinua Achebe', null],
        ], null, 'A1');

        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        $sheet->getComment('A1')->getText()->createTextRun('As printed on the student ID, e.g. 2021/000123. Must not already be on the roster.');
        $sheet->getComment('C1')->getText()->createTextRun('Optional. Leave blank to use the entry year chosen on the upload form.');

        foreach (range('A', 'C') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'roster').'.xlsx';

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function filename(): string
    {
        return 'student-roster-template.xlsx';
    }
}
