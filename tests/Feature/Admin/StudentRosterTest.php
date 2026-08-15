<?php

use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->department = Department::factory()->create();
});

/**
 * An .xlsx upload built from a table of rows.
 *
 * @param  list<list<mixed>>  $rows
 */
function rosterUpload(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

    $path = tempnam(sys_get_temp_dir(), 'roster-upload').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'roster.xlsx', null, null, true);
}

describe('adding one student', function () {
    it('creates an unclaimed roster row', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.students.store'), [
                'name' => 'Ada Lovelace',
                'reg_number' => '2021/000123',
                'dept_id' => $this->department->id,
                'entry_year' => 2021,
            ])
            ->assertSessionHas('success');

        $student = Student::query()->sole();

        expect($student->name)->toBe('Ada Lovelace')
            ->and($student->isClaimed())->toBeFalse();
    });

    it('refuses a registration number already on the roster', function () {
        Student::factory()->create(['reg_number' => '2021/000123']);

        $this->actingAs($this->admin)
            ->post(route('admin.students.store'), [
                'name' => 'Ada Lovelace',
                'reg_number' => '2021/000123',
                'dept_id' => $this->department->id,
                'entry_year' => 2021,
            ])
            ->assertSessionHasErrors('reg_number');

        expect(Student::query()->count())->toBe(1);
    });
});

describe('importing a cohort', function () {
    it('adds every row of a clean sheet', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.students.import'), [
                'sheet' => rosterUpload([
                    ['reg_number', 'name'],
                    ['2021/000101', 'Ada Lovelace'],
                    ['2021/000102', 'Chinua Achebe'],
                ]),
                'dept_id' => $this->department->id,
                'entry_year' => 2021,
            ])
            ->assertSessionHas('success');

        expect(Student::query()->count())->toBe(2);
    });

    it('shows the row-by-row errors and writes nothing', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.students.import'), [
                'sheet' => rosterUpload([
                    ['reg_number', 'name'],
                    ['2021/000101', 'Ada Lovelace'],
                    ['2021/000102', ''],
                ]),
                'dept_id' => $this->department->id,
                'entry_year' => 2021,
            ])
            ->assertSessionHas('error')
            ->assertSessionHas('importErrors');

        expect(Student::query()->count())->toBe(0);
    });

    it('reports a whole-sheet problem without row errors', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.students.import'), [
                'sheet' => rosterUpload([
                    ['reg_number'],
                    ['2021/000101'],
                ]),
                'dept_id' => $this->department->id,
                'entry_year' => 2021,
            ])
            ->assertSessionHas('error')
            ->assertSessionMissing('importErrors');
    });

    it('offers a template to download', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.students.template'))
            ->assertDownload('student-roster-template.xlsx');
    });
});

it('lists the roster with its claimed state', function () {
    Student::factory()->create(['reg_number' => '2021/000001']);
    Student::factory()->unclaimed()->create(['reg_number' => '2021/000002']);

    $this->actingAs($this->admin)
        ->get(route('admin.users'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users')
            ->has('students', 2)
            ->where('students.0.claimed', true)
            ->where('students.1.claimed', false)
        );
});

it('is closed to everyone but administrators', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->post(route('admin.students.store'), [])->assertForbidden();
    $this->actingAs($user)->post(route('admin.students.import'), [])->assertForbidden();
    $this->actingAs($user)->get(route('admin.students.template'))->assertForbidden();
})->with(['student', 'lecturer', 'examOfficer', 'ictDirector']);
