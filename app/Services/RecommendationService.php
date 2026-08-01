<?php

namespace App\Services;

use App\Models\Recommendation;
use App\Models\Student;
use App\Services\Llm\LlmDriver;
use App\Services\Llm\LlmUnavailable;
use App\Support\Grading;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The recommendation layer of README §8.
 *
 *     SQL computes the facts  →  PHP rules decide what is true
 *                             →  LLM phrases it  →  cache
 *
 * The defensibility of this feature rests on every fact in the advice being
 * traceable to a query, so the boundaries are strict:
 *
 *  - facts() runs aggregates in PostgreSQL and returns numbers
 *  - flags() turns those numbers into named conditions, in PHP, deterministically
 *  - the model receives ONLY the facts and flags, never a raw result row, and
 *    is instructed to invent no grade and no course
 *  - if the model is unreachable, rule-based phrasing renders instead, so the
 *    system works with no internet at all
 */
class RecommendationService
{
    private const AT_RISK_CGPA = 1.50;

    private const WEAK_GRADE_POINT = 2;

    public function __construct(
        private readonly LlmDriver $llm,
        private readonly VisualizationService $visualization,
    ) {}

    /**
     * Cached advice for a student in a session, generating it if absent.
     */
    public function forStudent(Student $student, string $session, bool $refresh = false): Recommendation
    {
        $existing = Recommendation::query()
            ->where('student_id', $student->getKey())
            ->where('session', $session)
            ->first();

        if ($existing !== null && ! $refresh) {
            return $existing;
        }

        $facts = $this->facts($student);
        $flags = $this->flags($facts);

        [$body, $source] = $this->phrase($facts, $flags);

        return DB::transaction(fn () => Recommendation::query()->updateOrCreate(
            ['student_id' => $student->getKey(), 'session' => $session],
            [
                'facts' => $facts,
                'flags' => $flags,
                'body' => $body,
                'source' => $source,
                'generated_at' => now(),
            ],
        ));
    }

    /**
     * Step 1 — the facts, computed in SQL and nowhere else.
     *
     * @return array{cgpa: float, classification: string, semesters_completed: int,
     *               cgpa_slope: float, recent_gpas: list<float>,
     *               weakest_courses: list<array{course_code: string, grade: string, grade_point: int}>,
     *               failed_courses: list<string>, credit_units_passed: int}
     */
    public function facts(Student $student): array
    {
        $trend = $this->visualization->cgpaTrend($student->getKey());
        $cgpa = $trend === [] ? 0.0 : (float) end($trend)['cgpa'];

        // The last three semesters' GPA, for the slope.
        $recentGpas = array_map(
            fn (array $point) => (float) $point['gpa'],
            array_slice($trend, -3),
        );

        $weakest = DB::select(<<<'SQL'
            SELECT c.course_code, res.grade, res.grade_point
            FROM results res
            JOIN registrations reg ON reg.id = res.registration_id
            JOIN courses c         ON c.id  = reg.course_id
            WHERE res.status = 'published'
              AND reg.student_id = :student_id
              AND res.grade_point <= :weak_point
            ORDER BY res.grade_point ASC, c.course_code ASC
            LIMIT 5
        SQL, ['student_id' => $student->getKey(), 'weak_point' => self::WEAK_GRADE_POINT]);

        $creditsPassed = DB::scalar(<<<'SQL'
            SELECT COALESCE(SUM(c.credit_units), 0)
            FROM results res
            JOIN registrations reg ON reg.id = res.registration_id
            JOIN courses c         ON c.id  = reg.course_id
            WHERE res.status = 'published'
              AND reg.student_id = :student_id
              AND res.grade <> 'F'
        SQL, ['student_id' => $student->getKey()]);

        return [
            'cgpa' => round($cgpa, 2),
            'classification' => Grading::classification($cgpa),
            'semesters_completed' => count($trend),
            'cgpa_slope' => $this->slope($recentGpas),
            'recent_gpas' => $recentGpas,
            'weakest_courses' => array_map(fn (object $row) => [
                'course_code' => $row->course_code,
                'grade' => $row->grade,
                'grade_point' => (int) $row->grade_point,
            ], $weakest),
            'failed_courses' => array_values(array_unique(array_map(
                fn (object $row) => $row->course_code,
                array_filter($weakest, fn (object $row) => $row->grade === 'F'),
            ))),
            'credit_units_passed' => (int) $creditsPassed,
        ];
    }

    /**
     * Step 2 — rules. Deterministic PHP over the facts, no model involved.
     *
     * @param  array<string, mixed>  $facts
     * @return list<string>
     */
    public function flags(array $facts): array
    {
        $flags = [];

        if (count($facts['recent_gpas']) >= 2 && $facts['cgpa_slope'] < 0) {
            $flags[] = 'cgpa_declining_2_semesters';
        }

        if ($facts['cgpa'] > 0 && $facts['cgpa'] < self::AT_RISK_CGPA) {
            $flags[] = 'at_risk_below_1_5';
        }

        if ($facts['weakest_courses'] !== []) {
            $flags[] = 'weak_in:'.implode(',', array_column($facts['weakest_courses'], 'course_code'));
        }

        if ($facts['failed_courses'] !== []) {
            $flags[] = 'failed:'.implode(',', $facts['failed_courses']);
        }

        if ($facts['cgpa'] >= 4.50) {
            $flags[] = 'first_class_standing';
        }

        if ($facts['semesters_completed'] === 0) {
            $flags[] = 'no_published_results';
        }

        return $flags;
    }

    /**
     * Steps 3 and 5 — phrasing, with the fallback.
     *
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $flags
     * @return array{0: string, 1: string} the text and its source
     */
    private function phrase(array $facts, array $flags): array
    {
        if (in_array('no_published_results', $flags, strict: true)) {
            return [$this->fallbackText($facts, $flags), Recommendation::SOURCE_FALLBACK];
        }

        if (! $this->llm->isConfigured()) {
            return [$this->fallbackText($facts, $flags), Recommendation::SOURCE_FALLBACK];
        }

        try {
            $text = $this->llm->complete($this->systemPrompt(), $this->userPrompt($facts, $flags));

            return [$text, Recommendation::SOURCE_LLM];
        } catch (LlmUnavailable $exception) {
            Log::info('Recommendation phrasing fell back to rules.', ['reason' => $exception->getMessage()]);

            return [$this->fallbackText($facts, $flags), Recommendation::SOURCE_FALLBACK];
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are an academic adviser at the University of Nigeria, Nsukka, writing to a student about their own performance.

        Rules you must follow exactly:
        - Use ONLY the facts supplied in the message. Invent no grade, no course code, no score and no number of any kind.
        - Never state a figure that does not appear in the supplied facts.
        - Write exactly three sentences of plain, encouraging, practical advice.
        - Address the student directly as "you". Do not use headings, bullet points or markdown.
        PROMPT;
    }

    /**
     * The payload the model sees. Facts and flags only — no result rows, no
     * scores, no student identity.
     *
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $flags
     */
    public function userPrompt(array $facts, array $flags): string
    {
        $weak = array_map(
            fn (array $course) => "{$course['course_code']} (grade {$course['grade']})",
            $facts['weakest_courses'],
        );

        $lines = [
            'Current CGPA: '.number_format($facts['cgpa'], 2).' out of 5.00',
            'Classification: '.$facts['classification'],
            'Semesters completed: '.$facts['semesters_completed'],
            'Recent semester GPAs, oldest first: '.($facts['recent_gpas'] === [] ? 'none' : implode(', ', array_map(
                fn (float $gpa) => number_format($gpa, 2),
                $facts['recent_gpas'],
            ))),
            'Credit units passed: '.$facts['credit_units_passed'],
            'Weakest courses: '.($weak === [] ? 'none' : implode('; ', $weak)),
            'Flags: '.($flags === [] ? 'none' : implode(', ', $flags)),
        ];

        return implode("\n", $lines);
    }

    /**
     * Step 5 — the offline fallback. Deterministic, drawn from the same facts,
     * and good enough to stand alone if no model is ever installed.
     *
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $flags
     */
    public function fallbackText(array $facts, array $flags): string
    {
        if (in_array('no_published_results', $flags, strict: true)) {
            return 'You have no published results yet, so there is nothing to assess at this point. '
                .'Your advice will appear here once your first semester of results has been released. '
                .'In the meantime, keep up with your continuous assessment in every registered course.';
        }

        $cgpa = number_format($facts['cgpa'], 2);
        $sentences = [];

        $sentences[] = in_array('cgpa_declining_2_semesters', $flags, strict: true)
            ? "Your CGPA now stands at {$cgpa}, and it has fallen over your last "
                .count($facts['recent_gpas']).' semesters, which places you in the '
                .strtolower($facts['classification']).' band.'
            : "Your CGPA now stands at {$cgpa}, placing you in the "
                .strtolower($facts['classification']).' band after '
                .$facts['semesters_completed'].' completed semesters.';

        if ($facts['weakest_courses'] !== []) {
            $codes = array_column($facts['weakest_courses'], 'course_code');
            $sentences[] = 'Your weakest results are in '.$this->list($codes)
                .', so those are where focused revision and early help from your lecturers will pay off most.';
        } elseif (in_array('first_class_standing', $flags, strict: true)) {
            $sentences[] = 'You have no weak results on record, which puts you in a strong position to hold first class standing.';
        } else {
            $sentences[] = 'You have no results below a D on record, so your performance is steady across your registered courses.';
        }

        $sentences[] = match (true) {
            in_array('at_risk_below_1_5', $flags, strict: true) => 'You are below the 1.50 mark, so speak to your academic adviser this semester and agree a plan to recover the affected courses.',
            in_array('cgpa_declining_2_semesters', $flags, strict: true) => 'Act on this trend now rather than next session, because recovering a CGPA takes more credit units than protecting one.',
            in_array('first_class_standing', $flags, strict: true) => 'Keep your current study pattern and register your remaining courses early to protect this standing.',
            default => 'Keep your continuous assessment marks high, since they carry 30 of the 100 marks in every course.',
        };

        return implode(' ', $sentences);
    }

    /**
     * Slope of a short GPA series: the mean step between consecutive points.
     * Negative means the student is trending downward.
     *
     * @param  list<float>  $gpas
     */
    private function slope(array $gpas): float
    {
        if (count($gpas) < 2) {
            return 0.0;
        }

        $steps = [];

        for ($i = 1; $i < count($gpas); $i++) {
            $steps[] = $gpas[$i] - $gpas[$i - 1];
        }

        return round(array_sum($steps) / count($steps), 3);
    }

    /**
     * @param  list<string>  $items
     */
    private function list(array $items): string
    {
        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }
}
