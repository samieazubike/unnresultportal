<?php

use App\Models\Department;
use App\Models\Student;
use App\Services\ImportFailed;
use App\Services\StudentRosterImporter;
use App\Services\StudentRosterTemplate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The roster importer follows the same rule as the score importer (README
 * §10): reject and report, never silently skip, and never write a partial
 * sheet.
 */
beforeEach(function () {
    $this->importer = app(StudentRosterImporter::class);
    $this->department = Department::factory()->create();
});

/**
 * Write a temporary .xlsx from a table of rows.
 *
 * Named apart from ExcelResultImporterTest's makeSheet(): Pest test files
 * share one global function namespace, so a second makeSheet() would be a
 * fatal redeclare.
 *
 * @param  list<list<mixed>>  $rows
 */
function makeRosterSheet(array $rows): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

    $path = tempnam(sys_get_temp_dir(), 'roster-import').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

describe('a clean sheet', function () {
    it('adds every row to the roster, unclaimed', function () {
        $path = makeRosterSheet([
            ['reg_number', 'name'],
            ['2021/000101', 'Ada Lovelace'],
            ['2021/000102', 'Chinua Achebe'],
        ]);

        $outcome = $this->importer->import($path, $this->department, 2021);

        expect($outcome['imported'])->toBe(2)
            ->and($outcome['errors'])->toBe([])
            ->and(Student::query()->whereNull('user_id')->count())->toBe(2);

        $student = Student::query()->where('reg_number', '2021/000101')->sole();

        expect($student->name)->toBe('Ada Lovelace')
            ->and($student->dept_id)->toBe($this->department->id)
            ->and($student->entry_year)->toBe(2021)
            ->and($student->isClaimed())->toBeFalse();
    });

    it('lets a per-row entry year override the one on the form', function () {
        $path = makeRosterSheet([
            ['reg_number', 'name', 'entry_year'],
            ['2021/000101', 'Ada Lovelace', 2019],
            ['2021/000102', 'Chinua Achebe', null],
        ]);

        $this->importer->import($path, $this->department, 2021);

        expect(Student::query()->where('reg_number', '2021/000101')->value('entry_year'))->toBe(2019)
            ->and(Student::query()->where('reg_number', '2021/000102')->value('entry_year'))->toBe(2021);
    });

    it('ignores empty trailing rows', function () {
        $path = makeRosterSheet([
            ['reg_number', 'name'],
            ['2021/000101', 'Ada Lovelace'],
            [null, null],
            ['', ''],
        ]);

        expect($this->importer->import($path, $this->department, 2021))
            ->toMatchArray(['imported' => 1, 'errors' => []]);
    });
});

describe('a sheet that cannot be read at all', function () {
    it('refuses a file that is not a spreadsheet', function () {
        $path = tempnam(sys_get_temp_dir(), 'roster').'.xlsx';
        file_put_contents($path, 'not a spreadsheet');

        expect(fn () => $this->importer->import($path, $this->department, 2021))
            ->toThrow(ImportFailed::class);
    });

    it('refuses a sheet missing a required column', function () {
        $path = makeRosterSheet([
            ['reg_number'],
            ['2021/000101'],
        ]);

        expect(fn () => $this->importer->import($path, $this->department, 2021))
            ->toThrow(ImportFailed::class, 'name');
    });
});

describe('row-by-row validation', function () {
    it('reports a missing registration number', function () {
        $path = makeRosterSheet([
            ['reg_number', 'name'],
            ['', 'Ada Lovelace'],
        ]);

        $outcome = $this->importer->import($path, $this->department, 2021);

        expect($outcome['errors'])->toHaveCount(1)
            ->and($outcome['errors'][0]['row'])->toBe(2)
            ->and($outcome['errors'][0]['message'])->toContain('Registration number is missing');
    });

    it('reports a missing name', function () {
        $path = makeRosterSheet([
            ['reg_number', 'name'],
            ['2021/000101', ''],
        ]);

        expect($this->importer->import($path, $this->department, 2021)['errors'][0]['message'])
            ->toContain('Name is missing');
    });

    it('reports a duplicate inside the sheet, naming the earlier row', function () {
        $path = makeRosterSheet([
            ['reg_number', 'name'],
            ['2021/000101', 'Ada Lovelace'],
            ['2021/000101', 'Ada L'],
        ]);

        $outcome = $this->importer->import($path, $this->department, 2021);

        expect($outcome['errors'])->toHaveCount(1)
            ->and($outcome['errors'][0]['row'])->toBe(3)
            ->and($outcome['errors'][0]['message'])->toContain('already appears on row 2');
    });

    it('reports a registration number already on the roster', function () {
        Student::factory()->create(['reg_number' => '2021/000101']);

        $path = makeRosterSheet([
            ['reg_number', 'name'],
            ['2021/000101', 'Ada Lovelace'],
        ]);

        expect($this->importer->import($path, $this->department, 2021)['errors'][0]['message'])
            ->toContain('already on the roster');
    });

    it('reports an implausible entry year', function () {
        $path = makeRosterSheet([
            ['reg_number', 'name', 'entry_year'],
            ['2021/000101', 'Ada Lovelace', 1200],
            ['2021/000102', 'Chinua Achebe', 'soon'],
        ]);

        $outcome = $this->importer->import($path, $this->department, 2021);

        expect($outcome['errors'])->toHaveCount(2)
            ->and($outcome['errors'][0]['message'])->toContain('Entry year must be between')
            ->and($outcome['errors'][1]['message'])->toContain('Entry year must be a number');
    });

    it('writes nothing at all when any row is in error', function () {
        $path = makeRosterSheet([
            ['reg_number', 'name'],
            ['2021/000101', 'Ada Lovelace'],
            ['2021/000102', ''],
            ['2021/000103', 'Chinua Achebe'],
        ]);

        $outcome = $this->importer->import($path, $this->department, 2021);

        expect($outcome['imported'])->toBe(0)
            ->and($outcome['errors'])->toHaveCount(1)
            ->and(Student::query()->count())->toBe(0);
    });
});

it('builds a template with the columns the importer requires', function () {
    $path = app(StudentRosterTemplate::class)->build();

    $rows = IOFactory::load($path)
        ->getActiveSheet()
        ->toArray(null, true, true, false);

    expect($rows[0])->toBe(['reg_number', 'name', 'entry_year']);

    unlink($path);
});
