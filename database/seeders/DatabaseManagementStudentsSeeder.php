<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Student;
use App\Models\User;
use App\Services\CourseOfferingService;
use App\Support\AcademicLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fifty claimed test student accounts in the Database Management course's
 * department, at the course's level, registered for it through the course
 * offering so the admin registration screen shows the same picture.
 *
 * Every account signs in as dbstudentNN@unn.edu.ng with the password
 * "password". Safe to run more than once.
 *
 *   php artisan db:seed --class=DatabaseManagementStudentsSeeder
 */
class DatabaseManagementStudentsSeeder extends Seeder
{
    private const COUNT = 50;

    private const LEVEL = 400;

    private const SESSION = '2025/2026';

    private const SEMESTER = 1;

    public function run(CourseOfferingService $offerings): void
    {
        $course = Course::query()->where('course_title', 'Database Management')->firstOrFail();
        $entryYear = AcademicLevel::entryYearFor(self::LEVEL, self::SESSION);

        for ($number = 1; $number <= self::COUNT; $number++) {
            $email = sprintf('dbstudent%02d@unn.edu.ng', $number);
            $name = fake()->name();

            $user = User::query()->firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make('password'),
                'role' => UserRole::Student,
                'email_verified_at' => now(),
            ]);

            Student::query()->firstOrCreate(['user_id' => $user->id], [
                'name' => $user->name,
                'reg_number' => sprintf('%d/9%05d', $entryYear, $number),
                'dept_id' => $course->dept_id,
                'entry_year' => $entryYear,
            ]);
        }

        $existing = CourseOffering::query()
            ->where('dept_id', $course->dept_id)
            ->where('level', self::LEVEL)
            ->where('session', self::SESSION)
            ->where('semester', self::SEMESTER)
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $outcome = $offerings->sync(
            $course->dept_id,
            self::LEVEL,
            self::SESSION,
            self::SEMESTER,
            array_values(array_unique([...$existing, $course->id])),
        );

        $this->command?->info(sprintf(
            'Seeded %d students for %s; %d registration(s) created.',
            self::COUNT,
            $course->course_code,
            $outcome['created'],
        ));
    }
}
