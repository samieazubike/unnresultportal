<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Grading;
use Illuminate\Support\Facades\DB;

/**
 * The five visualization capabilities of README §7.
 *
 * Every method returns a small, chart-ready array, and every aggregate is
 * computed by PostgreSQL. §2 and §16 are explicit that pulling thousands of
 * result rows into PHP to sum them is a design violation, so nothing here
 * loops over results — the database returns a handful of rows already
 * aggregated.
 *
 * Only status = 'published' counts, everywhere (§9).
 */
class VisualizationService
{
    /**
     * 1. CGPA trend for one student — the core visualization.
     *
     * This is the README §6 reference query. It is kept verbatim and readable
     * rather than rebuilt in the query builder, because it is the query most
     * likely to be inspected: a CTE computes each semester's weighted points
     * and credit units, then a window function accumulates them across
     * semesters to give the running CGPA beside the per-semester GPA.
     *
     * @return list<array{session: string, semester: int, gpa: float, cgpa: float, label: string}>
     */
    public function cgpaTrend(int $studentId): array
    {
        $rows = DB::select(<<<'SQL'
            WITH sem AS (
                SELECT reg.student_id,
                       reg.session,
                       reg.semester,
                       SUM(res.grade_point * c.credit_units) AS weighted_points,
                       SUM(c.credit_units)                   AS credit_units
                FROM results res
                JOIN registrations reg ON reg.id = res.registration_id
                JOIN courses c         ON c.id  = reg.course_id
                WHERE res.status = 'published'
                  AND reg.student_id = :student_id
                GROUP BY reg.student_id, reg.session, reg.semester
            )
            SELECT session,
                   semester,
                   ROUND(weighted_points::numeric / NULLIF(credit_units, 0), 2) AS gpa,
                   ROUND(
                       (SUM(weighted_points) OVER w)::numeric
                       / NULLIF(SUM(credit_units) OVER w, 0), 2
                   ) AS cgpa
            FROM sem
            WINDOW w AS (ORDER BY session, semester ROWS UNBOUNDED PRECEDING)
            ORDER BY session, semester
        SQL, ['student_id' => $studentId]);

        return array_map(fn (object $row) => [
            'session' => $row->session,
            'semester' => (int) $row->semester,
            'gpa' => (float) $row->gpa,
            'cgpa' => (float) $row->cgpa,
            'label' => $this->semesterLabel($row->session, (int) $row->semester),
        ], $rows);
    }

    /**
     * The student's current CGPA — the last point of the trend.
     */
    public function currentCgpa(int $studentId): float
    {
        $trend = $this->cgpaTrend($studentId);

        return $trend === [] ? 0.0 : (float) end($trend)['cgpa'];
    }

    /**
     * 2. Grade distribution for a course in a session.
     *
     * Grades with no rows are filled in at zero so the chart keeps a stable
     * A-to-F axis instead of collapsing to whatever happened to occur.
     *
     * @return list<array{grade: string, count: int}>
     */
    public function gradeDistribution(int $courseId, ?string $session = null): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT res.grade, COUNT(*) AS count
            FROM results res
            JOIN registrations reg ON reg.id = res.registration_id
            WHERE reg.course_id = :course_id
              AND (:session::text IS NULL OR reg.session = :session)
              AND res.status = 'published'
            GROUP BY res.grade
            ORDER BY res.grade
        SQL, ['course_id' => $courseId, 'session' => $session]);

        $counts = array_column(
            array_map(fn (object $row) => ['grade' => $row->grade, 'count' => (int) $row->count], $rows),
            'count',
            'grade',
        );

        return array_map(
            fn (string $grade) => ['grade' => $grade, 'count' => $counts[$grade] ?? 0],
            Grading::grades(),
        );
    }

    /**
     * 3a. Departmental performance — average student CGPA and grade counts.
     *
     * The inner aggregate reduces to one row per student, so the outer
     * average is a mean of student CGPAs rather than a mean of raw grade
     * points, which would silently weight prolific students more heavily.
     *
     * @return list<array{department: string, faculty: string, students: int, average_cgpa: float, passes: int, failures: int}>
     */
    public function departmentPerformance(): array
    {
        $rows = DB::select(<<<'SQL'
            WITH student_cgpa AS (
                SELECT s.id            AS student_id,
                       s.dept_id,
                       SUM(res.grade_point * c.credit_units)::numeric
                           / NULLIF(SUM(c.credit_units), 0) AS cgpa,
                       COUNT(*) FILTER (WHERE res.grade = 'F') AS failures,
                       COUNT(*) FILTER (WHERE res.grade <> 'F') AS passes
                FROM results res
                JOIN registrations reg ON reg.id = res.registration_id
                JOIN courses c         ON c.id  = reg.course_id
                JOIN students s        ON s.id  = reg.student_id
                WHERE res.status = 'published'
                GROUP BY s.id, s.dept_id
            )
            SELECT d.dept_name        AS department,
                   f.faculty_name     AS faculty,
                   COUNT(*)           AS students,
                   ROUND(AVG(sc.cgpa), 2) AS average_cgpa,
                   SUM(sc.passes)     AS passes,
                   SUM(sc.failures)   AS failures
            FROM student_cgpa sc
            JOIN departments d ON d.id = sc.dept_id
            JOIN faculties f   ON f.id = d.faculty_id
            GROUP BY d.dept_name, f.faculty_name
            ORDER BY average_cgpa DESC
        SQL);

        return array_map(fn (object $row) => [
            'department' => $row->department,
            'faculty' => $row->faculty,
            'students' => (int) $row->students,
            'average_cgpa' => (float) $row->average_cgpa,
            'passes' => (int) $row->passes,
            'failures' => (int) $row->failures,
        ], $rows);
    }

    /**
     * 3b. The same comparison rolled up to faculty level.
     *
     * @return list<array{faculty: string, departments: int, students: int, average_cgpa: float, passes: int, failures: int}>
     */
    public function facultyPerformance(): array
    {
        $rows = DB::select(<<<'SQL'
            WITH student_cgpa AS (
                SELECT s.id      AS student_id,
                       d.faculty_id,
                       s.dept_id,
                       SUM(res.grade_point * c.credit_units)::numeric
                           / NULLIF(SUM(c.credit_units), 0) AS cgpa,
                       COUNT(*) FILTER (WHERE res.grade = 'F') AS failures,
                       COUNT(*) FILTER (WHERE res.grade <> 'F') AS passes
                FROM results res
                JOIN registrations reg ON reg.id = res.registration_id
                JOIN courses c         ON c.id  = reg.course_id
                JOIN students s        ON s.id  = reg.student_id
                JOIN departments d     ON d.id  = s.dept_id
                WHERE res.status = 'published'
                GROUP BY s.id, d.faculty_id, s.dept_id
            )
            SELECT f.faculty_name              AS faculty,
                   COUNT(DISTINCT sc.dept_id)  AS departments,
                   COUNT(*)                    AS students,
                   ROUND(AVG(sc.cgpa), 2)      AS average_cgpa,
                   SUM(sc.passes)              AS passes,
                   SUM(sc.failures)            AS failures
            FROM student_cgpa sc
            JOIN faculties f ON f.id = sc.faculty_id
            GROUP BY f.faculty_name
            ORDER BY average_cgpa DESC
        SQL);

        return array_map(fn (object $row) => [
            'faculty' => $row->faculty,
            'departments' => (int) $row->departments,
            'students' => (int) $row->students,
            'average_cgpa' => (float) $row->average_cgpa,
            'passes' => (int) $row->passes,
            'failures' => (int) $row->failures,
        ], $rows);
    }

    /**
     * 4. Course failure-rate flagging.
     *
     * The COUNT(*) FILTER form is the README §7 query. Courses above the
     * admin-configured threshold (default 40%) come back flagged, ready for
     * the table's badge.
     *
     * @return list<array{course_code: string, course_title: string, session: string, enrolled: int, failed: int, failure_rate: float, flagged: bool}>
     */
    public function courseFailureRates(?string $session = null, ?float $threshold = null): array
    {
        $threshold ??= Setting::failureThreshold();

        $rows = DB::select(<<<'SQL'
            SELECT c.course_code,
                   c.course_title,
                   reg.session,
                   COUNT(*)                                        AS enrolled,
                   COUNT(*) FILTER (WHERE res.grade = 'F')         AS failed,
                   ROUND(100.0 * COUNT(*) FILTER (WHERE res.grade = 'F')
                         / NULLIF(COUNT(*), 0), 1)                 AS failure_rate
            FROM results res
            JOIN registrations reg ON reg.id = res.registration_id
            JOIN courses c         ON c.id  = reg.course_id
            WHERE res.status = 'published'
              AND (:session::text IS NULL OR reg.session = :session)
            GROUP BY c.course_code, c.course_title, reg.session
            ORDER BY failure_rate DESC
        SQL, ['session' => $session]);

        return array_map(fn (object $row) => [
            'course_code' => $row->course_code,
            'course_title' => $row->course_title,
            'session' => $row->session,
            'enrolled' => (int) $row->enrolled,
            'failed' => (int) $row->failed,
            'failure_rate' => (float) $row->failure_rate,
            'flagged' => (float) $row->failure_rate > $threshold,
        ], $rows);
    }

    /**
     * Semester GPA breakdown for one student, session by session.
     *
     * @return list<array{session: string, semester: int, gpa: float, credit_units: int, label: string}>
     */
    public function semesterGpas(int $studentId): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT reg.session,
                   reg.semester,
                   ROUND(SUM(res.grade_point * c.credit_units)::numeric
                         / NULLIF(SUM(c.credit_units), 0), 2) AS gpa,
                   SUM(c.credit_units) AS credit_units
            FROM results res
            JOIN registrations reg ON reg.id = res.registration_id
            JOIN courses c         ON c.id  = reg.course_id
            WHERE res.status = 'published'
              AND reg.student_id = :student_id
            GROUP BY reg.session, reg.semester
            ORDER BY reg.session, reg.semester
        SQL, ['student_id' => $studentId]);

        return array_map(fn (object $row) => [
            'session' => $row->session,
            'semester' => (int) $row->semester,
            'gpa' => (float) $row->gpa,
            'credit_units' => (int) $row->credit_units,
            'label' => $this->semesterLabel($row->session, (int) $row->semester),
        ], $rows);
    }

    /**
     * Headline counts for the dashboard stat tiles.
     *
     * @return array{students: int, courses: int, published_results: int, pending_review: int, flagged_courses: int}
     */
    public function institutionSummary(): array
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT (SELECT COUNT(*) FROM students)                                AS students,
                   (SELECT COUNT(*) FROM courses)                                 AS courses,
                   (SELECT COUNT(*) FROM results WHERE status = 'published')      AS published_results,
                   (SELECT COUNT(*) FROM results WHERE status = 'submitted')      AS pending_review,
                   (SELECT COUNT(*) FROM results WHERE status = 'approved')       AS awaiting_release
        SQL);

        return [
            'students' => (int) $row->students,
            'courses' => (int) $row->courses,
            'published_results' => (int) $row->published_results,
            'pending_review' => (int) $row->pending_review,
            'awaiting_release' => (int) $row->awaiting_release,
            'flagged_courses' => count(array_filter(
                $this->courseFailureRates(),
                fn (array $course) => $course['flagged'],
            )),
        ];
    }

    private function semesterLabel(string $session, int $semester): string
    {
        $ordinal = $semester === 1 ? '1st' : '2nd';

        return "{$session} {$ordinal}";
    }
}
