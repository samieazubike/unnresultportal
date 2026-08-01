<?php

use App\Enums\ResultStatus;
use App\Models\Course;
use App\Models\Registration;
use App\Models\Result;
use App\Models\ResultAudit;
use App\Models\User;
use App\Services\ResultIntegrityService;
use App\Services\ResultWorkflowService;
use Illuminate\Support\Facades\DB;

/**
 * README §9: approval and publication are separate states, and the separation
 * is a documented design decision that will be examined.
 */
beforeEach(function () {
    $this->workflow = app(ResultWorkflowService::class);
    $this->integrity = app(ResultIntegrityService::class);

    $this->head = User::factory()->lecturer()->create();
    $this->officer = User::factory()->examOfficer()->create();
    $this->director = User::factory()->ictDirector()->create();

    $this->course = Course::factory()->headedBy($this->head)->create();
});

function makeResult(Course $course, ResultStatus $status = ResultStatus::Draft, float $total = 75): Result
{
    $registration = Registration::factory()->create(['course_id' => $course->id]);

    return Result::factory()->scoring($total)->create([
        'registration_id' => $registration->id,
        'status' => $status,
    ]);
}

describe('the happy path', function () {
    it('moves a sheet from draft through to published', function () {
        $result = makeResult($this->course);
        $sheet = Result::query()->whereKey($result->id)->get();

        $this->workflow->submit($sheet, $this->head);
        expect($result->fresh()->status)->toBe(ResultStatus::Submitted);

        $this->workflow->approve($sheet->fresh(), $this->officer);
        expect($result->fresh()->status)->toBe(ResultStatus::Approved);

        $this->workflow->publish($sheet->fresh(), $this->director);
        expect($result->fresh()->status)->toBe(ResultStatus::Published)
            ->and($result->fresh()->published_by)->toBe($this->director->id);
    });

    it('returns a rejected sheet to draft with the note on the trail', function () {
        $result = makeResult($this->course, ResultStatus::Submitted);

        $this->workflow->reject(Result::query()->whereKey($result->id)->get(), $this->officer, 'CA scores look transposed.');

        expect($result->fresh()->status)->toBe(ResultStatus::Draft)
            ->and(ResultAudit::query()->where('result_id', $result->id)->where('action', 'rejection_note')->sole()->new_values['note'])
            ->toBe('CA scores look transposed.');
    });
});

describe('approval is not publication', function () {
    it('leaves an approved result unpublished', function () {
        $result = makeResult($this->course, ResultStatus::Submitted);

        $this->workflow->approve(Result::query()->whereKey($result->id)->get(), $this->officer);

        expect($result->fresh()->status)->toBe(ResultStatus::Approved)
            ->and($result->fresh()->published_by)->toBeNull()
            ->and($result->fresh()->isPublished())->toBeFalse();
    });

    it('refuses to publish a result that has not been approved', function () {
        $result = makeResult($this->course, ResultStatus::Submitted);

        expect(fn () => $this->workflow->publish(Result::query()->whereKey($result->id)->get(), $this->director))
            ->toThrow(RuntimeException::class);
    });

    it('freezes the signature at approval', function () {
        $result = makeResult($this->course, ResultStatus::Submitted);

        $this->workflow->approve(Result::query()->whereKey($result->id)->get(), $this->officer);

        expect($result->fresh()->signature->frozen)->toBeTrue()
            ->and($result->fresh()->signature->signed_by)->toBe($this->officer->id);
    });
});

describe('illegal transitions', function () {
    it('rejects a jump that skips a state', function () {
        $result = makeResult($this->course);

        expect(fn () => $this->workflow->approve(Result::query()->whereKey($result->id)->get(), $this->officer))
            ->toThrow(RuntimeException::class);
    });

    it('refuses to move a published result anywhere', function () {
        $result = makeResult($this->course, ResultStatus::Published);

        expect(fn () => $this->workflow->submit(Result::query()->whereKey($result->id)->get(), $this->head))
            ->toThrow(RuntimeException::class);
    });

    it('refuses to submit a sheet with a missing score', function () {
        $registration = Registration::factory()->create(['course_id' => $this->course->id]);
        $result = Result::query()->create([
            'registration_id' => $registration->id,
            'ca_score' => 20,
            'exam_score' => null,
            'status' => ResultStatus::Draft,
        ]);

        expect(fn () => $this->workflow->submit(Result::query()->whereKey($result->id)->get(), $this->head))
            ->toThrow(RuntimeException::class);
    });
});

describe('publication cannot alter a score', function () {
    it('leaves every score column byte-identical', function () {
        $result = makeResult($this->course, ResultStatus::Approved, total: 62);
        $this->integrity->freeze($result, $this->officer);

        $before = $result->only(['ca_score', 'exam_score', 'total_score', 'grade', 'grade_point']);

        $this->workflow->publish(Result::query()->whereKey($result->id)->get(), $this->director);

        expect($result->fresh()->only(['ca_score', 'exam_score', 'total_score', 'grade', 'grade_point']))
            ->toBe($before);
    });

    it('refuses to publish a result whose signature no longer matches', function () {
        // Scored a B, so promoting it to an A is a genuine change.
        $result = makeResult($this->course, ResultStatus::Approved, total: 62);
        $this->integrity->freeze($result, $this->officer);

        // Tampered with directly in the database after approval.
        DB::table('results')->where('id', $result->id)->update(['grade' => 'A', 'grade_point' => 5]);

        $outcome = $this->workflow->publish(Result::query()->whereKey($result->id)->get(), $this->director);

        expect($outcome['published'])->toBe(0)
            ->and($outcome['rejected'])->toBe([$result->id])
            ->and($result->fresh()->status)->toBe(ResultStatus::Approved);
    });

    it('publishes the intact results and holds back only the tampered one', function () {
        $clean = makeResult($this->course, ResultStatus::Approved);
        $dirty = makeResult($this->course, ResultStatus::Approved);

        foreach ([$clean, $dirty] as $result) {
            $this->integrity->freeze($result, $this->officer);
        }

        DB::table('results')->where('id', $dirty->id)->update(['total_score' => 99]);

        $outcome = $this->workflow->publish(Result::query()->whereKey([$clean->id, $dirty->id])->get(), $this->director);

        expect($outcome['published'])->toBe(1)
            ->and($outcome['rejected'])->toBe([$dirty->id])
            ->and($clean->fresh()->status)->toBe(ResultStatus::Published)
            ->and($dirty->fresh()->status)->toBe(ResultStatus::Approved);
    });

    it('refuses a publisher who holds neither publishing role', function () {
        $result = makeResult($this->course, ResultStatus::Approved);

        expect(fn () => $this->workflow->publish(
            Result::query()->whereKey($result->id)->get(),
            User::factory()->admin()->create(),
        ))->toThrow(RuntimeException::class);
    });
});
