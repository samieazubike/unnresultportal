<?php

namespace App\Services;

use App\Enums\ResultStatus;
use App\Models\Registration;
use App\Models\Result;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * What a student is allowed to see (README §9, §10).
 *
 * The rule this class exists to hold: until a result is published, the
 * student sees "awaiting release" and nothing more. An approved result is
 * academically accepted but deliberately not visible, and it must be absent
 * from the payload entirely rather than merely hidden in the interface.
 */
class StudentPortalService
{
    public function __construct(private readonly ResultIntegrityService $integrity) {}

    /**
     * Published results grouped by session and semester, ready for the portal.
     *
     * @return list<array{session: string, semester: int, label: string, gpa: float,
     *                    credit_units: int, courses: list<array<string, mixed>>}>
     */
    public function publishedResults(Student $student): array
    {
        $registrations = Registration::query()
            ->with(['course', 'result'])
            ->where('student_id', $student->getKey())
            ->whereHas('result', fn ($query) => $query->where('status', ResultStatus::Published))
            ->get();

        // A published result whose signature no longer matches is withheld
        // rather than shown: a student must never be handed a mark the
        // system cannot vouch for.
        $trustworthy = $registrations->filter(
            fn (Registration $registration) => $this->integrity->verify($registration->result)
        );

        return $trustworthy
            ->groupBy(fn (Registration $registration) => $registration->session.'|'.$registration->semester)
            ->map(function ($group) {
                /** @var Registration $first */
                $first = $group->first();

                $weighted = $group->sum(fn (Registration $r) => $r->result->grade_point * $r->course->credit_units);
                $units = $group->sum(fn (Registration $r) => $r->course->credit_units);

                return [
                    'session' => $first->session,
                    'semester' => $first->semester,
                    'label' => $first->session.' '.($first->semester === 1 ? '1st' : '2nd').' semester',
                    'gpa' => $units > 0 ? round($weighted / $units, 2) : 0.0,
                    'credit_units' => $units,
                    'courses' => $group
                        ->sortBy(fn (Registration $r) => $r->course->course_code)
                        ->map(fn (Registration $r) => [
                            'course_code' => $r->course->course_code,
                            'course_title' => $r->course->course_title,
                            'credit_units' => $r->course->credit_units,
                            'ca_score' => $r->result->ca_score,
                            'exam_score' => $r->result->exam_score,
                            'total_score' => $r->result->total_score,
                            'grade' => $r->result->grade,
                            'grade_point' => $r->result->grade_point,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy([['session', 'asc'], ['semester', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Sessions where the student has results that exist but have not been
     * released. The portal shows "awaiting release" for these and carries no
     * scores at all for them.
     *
     * @return list<array{session: string, semester: int, label: string, courses: int}>
     */
    public function sessionsAwaitingRelease(Student $student): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT reg.session, reg.semester, COUNT(*) AS courses
            FROM results res
            JOIN registrations reg ON reg.id = res.registration_id
            WHERE reg.student_id = :student_id
              AND res.status <> 'published'
            GROUP BY reg.session, reg.semester
            ORDER BY reg.session, reg.semester
        SQL, ['student_id' => $student->getKey()]);

        return array_map(fn (object $row) => [
            'session' => $row->session,
            'semester' => (int) $row->semester,
            'label' => $row->session.' '.((int) $row->semester === 1 ? '1st' : '2nd').' semester',
            'courses' => (int) $row->courses,
        ], $rows);
    }

    /**
     * Credit units passed, counting published non-F results only.
     */
    public function creditUnitsPassed(Student $student): int
    {
        return (int) DB::scalar(<<<'SQL'
            SELECT COALESCE(SUM(c.credit_units), 0)
            FROM results res
            JOIN registrations reg ON reg.id = res.registration_id
            JOIN courses c         ON c.id  = reg.course_id
            WHERE res.status = 'published'
              AND reg.student_id = :student_id
              AND res.grade <> 'F'
        SQL, ['student_id' => $student->getKey()]);
    }

    /**
     * Whether any published result for this student fails verification.
     */
    public function hasWithheldResults(Student $student): bool
    {
        $published = Result::query()
            ->with('signature')
            ->whereHas('registration', fn ($query) => $query->where('student_id', $student->getKey()))
            ->where('status', ResultStatus::Published)
            ->get();

        return $this->integrity->tampered($published)->isNotEmpty();
    }
}
