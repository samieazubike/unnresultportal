<?php

namespace App\Services;

use App\Enums\ResultStatus;
use App\Models\Result;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The result lifecycle from README §9.
 *
 *   draft ──submit──> submitted ──approve──> approved ──publish──> published
 *                          │
 *                          └──reject──> draft
 *
 * Two properties this class exists to guarantee:
 *
 *  1. Approval and publication are separate. §16 names collapsing them as
 *     defence-breaking, so there is no method here that does both.
 *  2. None of these transitions can alter a score. Every method touches
 *     status (and published_by) only — the score columns are never in scope.
 */
class ResultWorkflowService
{
    public function __construct(private readonly ResultIntegrityService $integrity) {}

    /**
     * Lecturer sends the sheet for review. Only complete rows travel.
     *
     * @param  Collection<int, Result>  $results
     * @return int the number of results moved
     */
    public function submit(Collection $results, User $lecturer): int
    {
        return $this->transition($results, ResultStatus::Submitted, $lecturer, function (Result $result) {
            if (! $result->hasScores()) {
                throw new RuntimeException(
                    'Every student must have both a CA and an exam score before the sheet can be submitted.'
                );
            }
        });
    }

    /**
     * Examination officer accepts the sheet academically.
     *
     * This does NOT release it to the student. The signature is frozen here:
     * from this point the stored hash records exactly what was approved, and
     * any later divergence is tampering rather than an edit.
     *
     * @param  Collection<int, Result>  $results
     */
    public function approve(Collection $results, User $officer): int
    {
        return $this->transition($results, ResultStatus::Approved, $officer, after: function (Result $result) use ($officer) {
            $this->integrity->freeze($result, $officer);
        });
    }

    /**
     * Examination officer returns the sheet to the lecturer with a note.
     *
     * @param  Collection<int, Result>  $results
     */
    public function reject(Collection $results, User $officer, string $note): int
    {
        return $this->transition($results, ResultStatus::Draft, $officer, after: function (Result $result) use ($officer, $note) {
            $result->audits()->create([
                'user_id' => $officer->getKey(),
                'action' => 'rejection_note',
                'new_values' => ['note' => $note],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Release approved results to students — a separate deliberate action by
     * the examination officer or the ICT director, recorded in published_by.
     *
     * A result whose signature no longer matches its contents is refused.
     * This is the point where the widest authority in the system is exercised,
     * so it is also where detection is enforced hardest.
     *
     * @param  Collection<int, Result>  $results
     * @return array{published: int, rejected: list<int>}
     */
    public function publish(Collection $results, User $publisher): array
    {
        if (! $publisher->canPublishResults()) {
            throw new RuntimeException('Only an examination officer or the ICT director may publish results.');
        }

        $results->loadMissing('signature');

        $tampered = $this->integrity->tampered($results);
        $publishable = $results->whereNotIn('id', $tampered->pluck('id'))->values();

        $published = $this->transition($publishable, ResultStatus::Published, $publisher, after: function (Result $result) use ($publisher) {
            $result->forceFill(['published_by' => $publisher->getKey()])->saveQuietly();
        });

        return [
            'published' => $published,
            'rejected' => $tampered->pluck('id')->all(),
        ];
    }

    /**
     * Move a set of results into a new status, refusing any illegal jump.
     *
     * @param  Collection<int, Result>  $results
     * @param  (callable(Result): void)|null  $before
     * @param  (callable(Result): void)|null  $after
     */
    private function transition(
        Collection $results,
        ResultStatus $target,
        User $actor,
        ?callable $before = null,
        ?callable $after = null,
    ): int {
        return DB::transaction(function () use ($results, $target, $before, $after) {
            $moved = 0;

            foreach ($results as $result) {
                if (! $result->status->canTransitionTo($target)) {
                    throw new RuntimeException(
                        "A {$result->status->value} result cannot become {$target->value}."
                    );
                }

                if ($before !== null) {
                    $before($result);
                }

                // Status only. No score column is ever in this update.
                $result->update(['status' => $target]);

                if ($after !== null) {
                    $after($result);
                }

                $moved++;
            }

            return $moved;
        });
    }
}
