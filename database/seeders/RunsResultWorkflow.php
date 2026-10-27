<?php

namespace Database\Seeders;

use App\Enums\ResultStatus;
use App\Models\Course;
use App\Models\Registration;
use App\Models\Result;
use App\Models\Student;
use App\Models\User;
use App\Services\GradingService;
use App\Services\ResultWorkflowService;
use App\Support\AcademicLevel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Takes seeded score sheets through the real workflow — scored by the
 * course head, submitted, approved and published — as the people who would
 * have done each step, on the days they would have done it. Grades,
 * signatures and the audit trail are therefore produced by the application,
 * never written by the seeder.
 *
 * @property-read GradingService $grading
 * @property-read ResultWorkflowService $workflow
 */
trait RunsResultWorkflow
{
    /**
     * Take one sheet as far as $target. Draft here means scored but not yet
     * submitted.
     *
     * @param  Collection<int, Registration>  $registrations
     * @param  callable(Student): float  $scoreFor
     */
    private function runSheet(
        Collection $registrations,
        Course $course,
        ResultStatus $target,
        callable $scoreFor,
        Carbon $publishedOn,
        User $officer,
        User $publisher,
    ): void {
        if ($registrations->isEmpty()) {
            return;
        }

        $head = $course->head;

        $this->actAs($head, $publishedOn->copy()->subDays(24));

        $results = new Collection;

        foreach ($registrations as $registration) {
            [$ca, $exam] = $this->split($scoreFor($registration->student));

            $results->push($this->grading->record($registration, $ca, $exam, $head));
        }

        if ($target === ResultStatus::Draft) {
            return;
        }

        $this->actAs($head, $publishedOn->copy()->subDays(18));
        $this->workflow->submit($this->fresh($results), $head);

        if ($target === ResultStatus::Submitted) {
            return;
        }

        $this->actAs($officer, $publishedOn->copy()->subDays(9));
        $this->workflow->approve($this->fresh($results), $officer);

        if ($target === ResultStatus::Approved) {
            return;
        }

        $this->actAs($publisher, $publishedOn);
        $this->workflow->publish($this->fresh($results), $publisher);
    }

    /**
     * First-semester sheets are released in March of the session's second
     * year, second-semester ones in September.
     */
    private function publicationDate(string $session, int $semester): Carbon
    {
        $year = AcademicLevel::sessionStartYear($session) + 1;

        return $semester === 1
            ? Carbon::create($year, 3, 10, 10, 30)
            : Carbon::create($year, 9, 8, 11, 0);
    }

    /**
     * Split a total into whole-mark CA (out of 30) and exam (out of 70).
     *
     * @return array{0: float, 1: float}
     */
    private function split(float $total): array
    {
        $ca = min(30.0, round($total * mt_rand(26, 32) / 100));
        $exam = $total - $ca;

        if ($exam > 70) {
            [$ca, $exam] = [$total - 70, 70.0];
        }

        return [$ca, $exam];
    }

    private function actAs(User $user, Carbon $at): void
    {
        Carbon::setTestNow($at);
        Auth::setUser($user);
    }

    private function stopActing(): void
    {
        Carbon::setTestNow();
        Auth::forgetUser();
    }

    /**
     * @param  Collection<int, Result>  $results
     * @return Collection<int, Result>
     */
    private function fresh(Collection $results): Collection
    {
        return Result::query()->with('signature')->whereKey($results->modelKeys())->get();
    }

    /**
     * A normally distributed sample (Box–Muller), seeded through mt_rand so
     * every run produces the same dataset.
     */
    private function normal(float $mean, float $deviation): float
    {
        $u = max(mt_rand() / mt_getrandmax(), 1e-9);
        $v = mt_rand() / mt_getrandmax();

        return $mean + $deviation * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
