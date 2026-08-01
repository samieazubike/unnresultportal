<?php

use App\Models\Course;
use App\Models\Registration;
use App\Models\Result;
use App\Models\Student;
use App\Models\User;
use App\Services\ExcelResultImporter;
use App\Services\ImportFailed;
use App\Services\ScoreSheetTemplate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * README §10: "Excel validation must reject and report, not silently skip:
 * unknown registration number, score out of range (CA > 30, exam > 70),
 * duplicate rows, missing columns. Show the lecturer a row-by-row error list
 * before anything is saved. Import inside a transaction."
 */
beforeEach(function () {
    $this->importer = app(ExcelResultImporter::class);
    $this->lecturer = User::factory()->lecturer()->create();
    $this->course = Course::factory()->headedBy($this->lecturer)->create();

    $this->students = collect(['2021/000101', '2021/000102', '2021/000103'])
        ->map(fn (string $reg) => Student::factory()->create(['reg_number' => $reg]));

    foreach ($this->students as $student) {
        Registration::factory()->create([
            'student_id' => $student->id,
            'course_id' => $this->course->id,
            'session' => '2025/2026',
            'semester' => 1,
        ]);
    }
});

/**
 * Write a temporary .xlsx from a table of rows.
 *
 * @param  list<list<mixed>>  $rows
 */
function makeSheet(array $rows): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

describe('a clean sheet', function () {
    it('imports every row and computes the grades', function () {
        $path = makeSheet([
            ['reg_number', 'ca_score', 'exam_score'],
            ['2021/000101', 24, 46],   // 70 => A
            ['2021/000102', 18, 40],   // 58 => C
            ['2021/000103', 10, 25],   // 35 => F
        ]);

        $outcome = $this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer);

        expect($outcome['imported'])->toBe(3)
            ->and($outcome['errors'])->toBe([])
            ->and(Result::query()->pluck('grade')->sort()->values()->all())->toBe(['A', 'C', 'F']);
    });

    it('never takes a grade from the file', function () {
        // The sheet claims an A; the scores say F. The scores win.
        $path = makeSheet([
            ['reg_number', 'ca_score', 'exam_score', 'grade', 'grade_point'],
            ['2021/000101', 5, 10, 'A', 5],
        ]);

        $this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer);

        expect(Result::query()->sole()->grade)->toBe('F')
            ->and(Result::query()->sole()->grade_point)->toBe(0);
    });

    it('accepts column names in any case, with spaces or hyphens', function () {
        $path = makeSheet([
            ['Reg Number', 'CA-Score', 'EXAM SCORE'],
            ['2021/000101', 24, 46],
        ]);

        expect($this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer)['imported'])->toBe(1);
    });

    it('tolerates stray whitespace in a registration number', function () {
        $path = makeSheet([
            ['reg_number', 'ca_score', 'exam_score'],
            [' 2021/000101 ', 24, 46],
        ]);

        expect($this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer)['imported'])->toBe(1);
    });

    it('ignores genuinely empty trailing rows', function () {
        $path = makeSheet([
            ['reg_number', 'ca_score', 'exam_score'],
            ['2021/000101', 24, 46],
            [null, null, null],
            ['', '', ''],
        ]);

        $outcome = $this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer);

        expect($outcome['imported'])->toBe(1)
            ->and($outcome['errors'])->toBe([]);
    });
});

describe('row-level validation reports rather than skips', function () {
    it('reports an unknown registration number', function () {
        $path = makeSheet([
            ['reg_number', 'ca_score', 'exam_score'],
            ['2021/999999', 24, 46],
        ]);

        $errors = $this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer)['errors'];

        expect($errors)->toHaveCount(1)
            ->and($errors[0]['row'])->toBe(2)
            ->and($errors[0]['message'])->toContain('No student with registration number 2021/999999');
    });

    it('reports a CA score above 30', function () {
        $path = makeSheet([
            ['reg_number', 'ca_score', 'exam_score'],
            ['2021/000101', 31, 46],
        ]);

        expect($this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer)['errors'][0]['message'])
            ->toContain('CA score must be between 0 and 30');
    });

    it('reports an exam score above 70', function () {
        $path = makeSheet([
            ['reg_number', 'ca_score', 'exam_score'],
            ['2021/000101', 24, 71],
        ]);

        expect($this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer)['errors'][0]['message'])
            ->toContain('Exam score must be between 0 and 70');
    });

    it('reports a duplicate row and names the row it repeats', function () {
        $path = makeSheet([
            ['reg_number', 'ca_score', 'exam_score'],
            ['2021/000101', 24, 46],
            ['2021/000101', 20, 40],
        ]);

        $errors = $this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer)['errors'];

        expect($errors)->toHaveCount(1)
            ->and($errors[0]['row'])->toBe(3)
            ->and($errors[0]['message'])->toContain('already appears on row 2');
    });

    it('reports a non-numeric score', function () {
        $path = makeSheet([
            ['reg_number', 'ca_score', 'exam_score'],
            ['2021/000101', 'absent', 46],
        ]);

        expect($this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer)['errors'][0]['message'])
            ->toContain('CA score must be a number');
    });

    it('reports a missing score rather than defaulting it to zero', function () {
        $path = makeSheet([
            ['reg_number', 'ca_score', 'exam_score'],
            ['2021/000101', 24, null],
        ]);

        expect($this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer)['errors'][0]['message'])
            ->toContain('Exam score is missing');
    });

    it('collects every problem in one pass rather than stopping at the first', function () {
        $path = makeSheet([
            ['reg_number', 'ca_score', 'exam_score'],
            ['2021/999999', 24, 46],   // unknown
            ['2021/000102', 45, 46],   // CA out of range
            ['2021/000103', 24, 90],   // exam out of range
        ]);

        $errors = $this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer)['errors'];

        expect($errors)->toHaveCount(3)
            ->and(array_column($errors, 'row'))->toBe([2, 3, 4]);
    });
});

describe('nothing is written while any row is in error', function () {
    it('saves no result at all when one row of many fails', function () {
        $path = makeSheet([
            ['reg_number', 'ca_score', 'exam_score'],
            ['2021/000101', 24, 46],   // valid
            ['2021/000102', 18, 40],   // valid
            ['2021/000103', 99, 46],   // invalid
        ]);

        $outcome = $this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer);

        expect($outcome['imported'])->toBe(0)
            ->and($outcome['errors'])->toHaveCount(1)
            ->and(Result::count())->toBe(0);
    });

    it('previews without writing anything', function () {
        $path = makeSheet([
            ['reg_number', 'ca_score', 'exam_score'],
            ['2021/000101', 24, 46],
        ]);

        $preview = $this->importer->preview($path, $this->course, '2025/2026', 1);

        expect($preview['rows'])->toHaveCount(1)
            ->and($preview['rows'][0]['student_name'])->not->toBeEmpty()
            ->and(Result::count())->toBe(0);
    });
});

describe('whole-sheet problems', function () {
    it('rejects a sheet missing a required column', function () {
        $path = makeSheet([
            ['reg_number', 'ca_score'],
            ['2021/000101', 24],
        ]);

        expect(fn () => $this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer))
            ->toThrow(ImportFailed::class, 'missing required column(s): exam_score');
    });

    it('rejects a file that is not a spreadsheet', function () {
        $path = tempnam(sys_get_temp_dir(), 'notxlsx');
        file_put_contents($path, 'this is not a spreadsheet');

        expect(fn () => $this->importer->import($path, $this->course, '2025/2026', 1, $this->lecturer))
            ->toThrow(ImportFailed::class);
    });
});

describe('the downloadable template', function () {
    it('round-trips through the importer', function () {
        $path = app(ScoreSheetTemplate::class)->build($this->course, '2025/2026', 1);

        $preview = $this->importer->preview($path, $this->course, '2025/2026', 1);

        // Every student appears, and every row is blank so far, so each is
        // reported as a missing score rather than silently dropped.
        expect($preview['errors'])->toHaveCount(3)
            ->and($preview['errors'][0]['message'])->toContain('CA score is missing');
    });

    it('names the file after the course, session and semester', function () {
        expect(app(ScoreSheetTemplate::class)->filename($this->course, '2025/2026', 1))
            ->toBe(str_replace(' ', '', $this->course->course_code).'-2025-2026-semester-1-scores.xlsx');
    });
});
