<?php

namespace App\Observers;

use App\Models\Result;
use App\Models\ResultAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes the append-only audit trail. Every score write and every status
 * transition is recorded with its actor, the previous and new values, and the
 * originating IP.
 *
 * Nothing in the application ever updates or deletes a ResultAudit row.
 */
class ResultObserver
{
    /**
     * The score columns worth recording a before/after for.
     *
     * @var list<string>
     */
    private const SCORE_COLUMNS = ['ca_score', 'exam_score', 'total_score', 'grade', 'grade_point'];

    public function created(Result $result): void
    {
        $this->record($result, 'created', newValues: $result->only(self::SCORE_COLUMNS));
    }

    public function updated(Result $result): void
    {
        $changed = $result->getChanges();

        $scoreChanges = array_intersect_key($changed, array_flip(self::SCORE_COLUMNS));
        $statusChanged = array_key_exists('status', $changed);

        if ($scoreChanges === [] && ! $statusChanged) {
            return;
        }

        $original = $result->getOriginal();

        $this->record(
            $result,
            action: $statusChanged ? $this->transitionAction($result) : 'scores_updated',
            oldValues: array_intersect_key($original, $scoreChanges),
            newValues: $scoreChanges,
            fromStatus: $statusChanged ? $this->statusValue($original['status'] ?? null) : null,
            toStatus: $statusChanged ? $result->status->value : null,
        );
    }

    /**
     * Name the transition after the workflow verb that caused it (§9), so the
     * trail reads as submit / approve / reject / publish rather than a
     * generic update.
     */
    private function transitionAction(Result $result): string
    {
        $from = $this->statusValue($result->getOriginal('status'));
        $to = $result->status->value;

        return match (true) {
            $from === 'draft' && $to === 'submitted' => 'submitted',
            $from === 'submitted' && $to === 'approved' => 'approved',
            $from === 'submitted' && $to === 'draft' => 'rejected',
            $from === 'approved' && $to === 'published' => 'published',
            default => 'status_changed',
        };
    }

    private function statusValue(mixed $status): ?string
    {
        return match (true) {
            $status === null => null,
            is_string($status) => $status,
            default => $status->value,
        };
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function record(
        Result $result,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
    ): void {
        ResultAudit::query()->create([
            'result_id' => $result->getKey(),
            'user_id' => Auth::id(),
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
