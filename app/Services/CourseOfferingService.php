<?php

namespace App\Services;

use App\Enums\RegistrationSource;
use App\Models\CourseOffering;
use App\Models\Registration;
use App\Models\Student;
use App\Support\AcademicLevel;
use Illuminate\Support\Facades\DB;

/**
 * Turns an administrator's pre-selected course list into registrations.
 *
 * Saving an offering registers the matching cohort straight away, so every
 * student is on the lecturer's score sheet whether or not they ever open the
 * registration page. Students may then add and drop while the window is open.
 *
 * Re-saving is safe to repeat and respects what students have done since:
 *
 *  - a student with no registration yet for the semester gets the full list
 *    (this is how students added to the roster later are picked up);
 *  - everyone else gets only the courses newly added to the offering, so a
 *    course a student deliberately dropped is not forced back on them;
 *  - a course taken off the offering is removed only from registrations the
 *    offering itself created, and never once a result exists.
 */
class CourseOfferingService
{
    /**
     * @param  list<int>  $courseIds
     * @return array{students: int, created: int, removed: int}
     */
    public function sync(int $deptId, int $level, string $session, int $semester, array $courseIds): array
    {
        $courseIds = array_values(array_unique(array_map('intval', $courseIds)));

        return DB::transaction(function () use ($deptId, $level, $session, $semester, $courseIds) {
            $offering = CourseOffering::query()
                ->where('dept_id', $deptId)
                ->where('level', $level)
                ->where('session', $session)
                ->where('semester', $semester);

            $existing = (clone $offering)->pluck('course_id')->map(fn ($id) => (int) $id)->all();
            $added = array_values(array_diff($courseIds, $existing));
            $removed = array_values(array_diff($existing, $courseIds));

            (clone $offering)->whereIn('course_id', $removed)->delete();

            CourseOffering::query()->insert(array_map(fn (int $courseId) => [
                'dept_id' => $deptId,
                'level' => $level,
                'session' => $session,
                'semester' => $semester,
                'course_id' => $courseId,
            ], $added));

            $students = Student::query()
                ->where('dept_id', $deptId)
                ->where('entry_year', AcademicLevel::entryYearFor($level, $session))
                ->withCount(['registrations as semester_registrations' => fn ($query) => $query
                    ->where('session', $session)
                    ->where('semester', $semester)])
                ->get(['id']);

            $rows = $students->flatMap(fn (Student $student) => array_map(fn (int $courseId) => [
                'student_id' => $student->getKey(),
                'course_id' => $courseId,
                'session' => $session,
                'semester' => $semester,
                'source' => RegistrationSource::Offering->value,
            ], (int) $student->getAttribute('semester_registrations') === 0 ? $courseIds : $added));

            $created = 0;

            foreach ($rows->chunk(500) as $chunk) {
                $created += Registration::query()->insertOrIgnore($chunk->values()->all());
            }

            $deleted = Registration::query()
                ->whereIn('student_id', $students->modelKeys())
                ->whereIn('course_id', $removed)
                ->where('session', $session)
                ->where('semester', $semester)
                ->where('source', RegistrationSource::Offering)
                ->whereDoesntHave('result')
                ->delete();

            return [
                'students' => $students->count(),
                'created' => $created,
                'removed' => $deleted,
            ];
        });
    }
}
