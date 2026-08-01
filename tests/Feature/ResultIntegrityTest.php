<?php

use App\Enums\ResultStatus;
use App\Models\Course;
use App\Models\Registration;
use App\Models\Result;
use App\Models\ResultAudit;
use App\Models\User;
use App\Services\GradingService;
use App\Services\ResultIntegrityService;
use App\Support\ResultIntegrity;
use Illuminate\Support\Facades\DB;

/**
 * The tamper-evidence claims, asserted directly.
 *
 * The threat is someone with database access altering an approved score.
 * These tests prove the application notices.
 */
beforeEach(function () {
    $this->integrity = app(ResultIntegrityService::class);
    $this->grading = app(GradingService::class);

    $this->head = User::factory()->lecturer()->create();
    $this->course = Course::factory()->headedBy($this->head)->create();
    $this->registration = Registration::factory()->create(['course_id' => $this->course->id]);
});

describe('signature', function () {
    it('matches a result the application wrote itself', function () {
        $result = $this->grading->record($this->registration, 24, 46, $this->head);

        expect($this->integrity->verify($result->fresh()))->toBeTrue();
    });

    it('detects a score changed directly in the database', function () {
        $result = $this->grading->record($this->registration, 24, 46, $this->head);

        // A direct UPDATE, bypassing the application entirely — the ICT
        // director scenario. The signature cannot be recomputed without the
        // application key, so the row no longer matches its hash.
        DB::table('results')->where('id', $result->id)->update([
            'total_score' => 95,
            'grade' => 'A',
            'grade_point' => 5,
        ]);

        expect($this->integrity->verify($result->fresh()))->toBeFalse();
    });

    it('survives a legitimate edit, because the application re-signs', function () {
        $result = $this->grading->record($this->registration, 24, 46, $this->head);
        $this->grading->record($this->registration->fresh(), 28, 60, $this->head);

        expect($this->integrity->verify($result->fresh()))->toBeTrue();
    });

    it('freezes at approval and stops tracking later writes', function () {
        $result = $this->grading->record($this->registration, 24, 46, $this->head);
        $approver = User::factory()->examOfficer()->create();

        $this->integrity->freeze($result, $approver);
        $frozenHash = $result->fresh()->signature->integrity_hash;

        // Signing again without an explicit freeze must not move the seal.
        $this->integrity->sign($result->fresh(), $this->head);

        expect($result->fresh()->signature->integrity_hash)->toBe($frozenHash)
            ->and($result->fresh()->signature->frozen)->toBeTrue()
            ->and($result->fresh()->signature->signed_by)->toBe($approver->id);
    });

    it('treats an unsigned result as intact', function () {
        $result = Result::factory()->create(['registration_id' => $this->registration->id]);

        expect($result->signature)->toBeNull()
            ->and($this->integrity->verify($result))->toBeTrue();
    });

    it('produces a stable hash for unchanged data', function () {
        $result = $this->grading->record($this->registration, 24, 46, $this->head);

        expect(ResultIntegrity::hash($result))->toBe(ResultIntegrity::hash($result->fresh()));
    });

    it('separates intact results from tampered ones', function () {
        $clean = $this->grading->record($this->registration, 24, 46, $this->head);

        $other = Registration::factory()->create(['course_id' => $this->course->id]);
        $dirty = $this->grading->record($other, 20, 40, $this->head);
        DB::table('results')->where('id', $dirty->id)->update(['grade' => 'A']);

        $all = Result::query()->with('signature')->get();

        expect($this->integrity->tampered($all)->pluck('id')->all())->toBe([$dirty->id])
            ->and($this->integrity->intact($all)->pluck('id')->all())->toBe([$clean->id]);
    });
});

describe('audit trail', function () {
    it('records the creation of a result with its scores', function () {
        $this->actingAs($this->head);
        $result = $this->grading->record($this->registration, 24, 46, $this->head);

        $audit = ResultAudit::query()->where('result_id', $result->id)->sole();

        expect($audit->action)->toBe('created')
            ->and($audit->user_id)->toBe($this->head->id)
            ->and($audit->new_values['grade'])->toBe('A');
    });

    it('records a score change with both the old and new value', function () {
        $this->actingAs($this->head);
        $result = $this->grading->record($this->registration, 10, 20, $this->head);
        $this->grading->record($this->registration->fresh(), 28, 60, $this->head);

        $audit = ResultAudit::query()
            ->where('result_id', $result->id)
            ->where('action', 'scores_updated')
            ->sole();

        expect($audit->old_values['grade'])->toBe('F')
            ->and($audit->new_values['grade'])->toBe('A')
            ->and($audit->old_values['total_score'])->not->toBe($audit->new_values['total_score']);
    });

    it('names each workflow transition after the verb that caused it', function () {
        $result = $this->grading->record($this->registration, 24, 46, $this->head);

        foreach ([ResultStatus::Submitted, ResultStatus::Approved, ResultStatus::Published] as $status) {
            $result->update(['status' => $status]);
        }

        expect(ResultAudit::query()->where('result_id', $result->id)->pluck('action')->all())
            ->toContain('submitted', 'approved', 'published');
    });

    it('records a rejection as a return to draft', function () {
        $result = $this->grading->record($this->registration, 24, 46, $this->head);
        $result->update(['status' => ResultStatus::Submitted]);
        $result->update(['status' => ResultStatus::Draft]);

        expect(ResultAudit::query()->where('result_id', $result->id)->pluck('action')->all())
            ->toContain('rejected');
    });

    it('ignores updates that touch neither scores nor status', function () {
        $result = $this->grading->record($this->registration, 24, 46, $this->head);
        $before = ResultAudit::query()->where('result_id', $result->id)->count();

        $result->touch();

        expect(ResultAudit::query()->where('result_id', $result->id)->count())->toBe($before);
    });
});

describe('edit lock', function () {
    it('refuses a score write once the result is approved', function () {
        $result = $this->grading->record($this->registration, 24, 46, $this->head);
        $result->update(['status' => ResultStatus::Approved]);

        expect(fn () => $this->grading->record($this->registration->fresh(), 10, 10, $this->head))
            ->toThrow(RuntimeException::class);
    });

    it('allows a score write while the result is still submitted', function () {
        $result = $this->grading->record($this->registration, 24, 46, $this->head);
        $result->update(['status' => ResultStatus::Submitted]);

        $updated = $this->grading->record($this->registration->fresh(), 28, 60, $this->head);

        expect($updated->total_score)->toBe(88.0);
    });
});
