<?php

use App\Enums\ResultStatus;
use App\Models\Course;
use App\Models\Registration;
use App\Models\Result;
use App\Models\User;
use Database\Factories\CourseFactory;

/**
 * Who may write a score.
 *
 * The claim being defended: no one outside the course's teaching team can
 * alter a mark — not the administrator, not the examination officer, and in
 * particular not the ICT director, whose access is otherwise the widest in
 * the system.
 */
beforeEach(function () {
    $this->head = User::factory()->lecturer()->create();
    $this->coLecturer = User::factory()->lecturer()->create();
    $this->outsider = User::factory()->lecturer()->create();

    $this->course = Course::factory()->create();
    CourseFactory::assignTeam($this->course, [$this->head, $this->coLecturer], head: $this->head);

    $this->registration = Registration::factory()->create(['course_id' => $this->course->id]);
    $this->result = Result::factory()->draft()->create(['registration_id' => $this->registration->id]);
});

describe('score writes', function () {
    it('allows any lecturer on the teaching team', function () {
        expect($this->head->can('update', $this->result))->toBeTrue()
            ->and($this->coLecturer->can('update', $this->result))->toBeTrue();
    });

    it('refuses a lecturer who does not teach the course', function () {
        expect($this->outsider->can('update', $this->result))->toBeFalse();
    });

    it('refuses every non-lecturer role without exception', function (string $state) {
        $user = User::factory()->{$state}()->create();

        expect($user->can('update', $this->result))->toBeFalse();
    })->with(['admin', 'examOfficer', 'ictDirector', 'student']);

    it('refuses everyone once the result is approved', function () {
        $this->result->update(['status' => ResultStatus::Approved]);

        expect($this->head->fresh()->can('update', $this->result->fresh()))->toBeFalse()
            ->and($this->coLecturer->fresh()->can('update', $this->result->fresh()))->toBeFalse();
    });

    it('still allows the team while the result is submitted', function () {
        $this->result->update(['status' => ResultStatus::Submitted]);

        expect($this->head->can('update', $this->result->fresh()))->toBeTrue()
            ->and($this->coLecturer->can('update', $this->result->fresh()))->toBeTrue();
    });
});

describe('submission', function () {
    it('is the course head\'s alone', function () {
        expect($this->head->can('submit', $this->result))->toBeTrue()
            ->and($this->coLecturer->can('submit', $this->result))->toBeFalse()
            ->and($this->outsider->can('submit', $this->result))->toBeFalse();
    });

    it('is unavailable once the sheet has left draft', function () {
        $this->result->update(['status' => ResultStatus::Submitted]);

        expect($this->head->can('submit', $this->result->fresh()))->toBeFalse();
    });
});

describe('approval', function () {
    it('belongs to the examination officer only', function () {
        $this->result->update(['status' => ResultStatus::Submitted]);
        $result = $this->result->fresh();

        expect(User::factory()->examOfficer()->create()->can('approve', $result))->toBeTrue()
            ->and(User::factory()->ictDirector()->create()->can('approve', $result))->toBeFalse()
            ->and(User::factory()->admin()->create()->can('approve', $result))->toBeFalse()
            ->and($this->head->can('approve', $result))->toBeFalse();
    });
});

describe('publication', function () {
    it('belongs to the examination officer and the ICT director', function () {
        $this->result->update(['status' => ResultStatus::Approved]);
        $result = $this->result->fresh();

        expect(User::factory()->examOfficer()->create()->can('publish', $result))->toBeTrue()
            ->and(User::factory()->ictDirector()->create()->can('publish', $result))->toBeTrue()
            ->and(User::factory()->admin()->create()->can('publish', $result))->toBeFalse()
            ->and($this->head->can('publish', $result))->toBeFalse();
    });

    it('is unavailable until the result has been approved', function () {
        $this->result->update(['status' => ResultStatus::Submitted]);

        expect(User::factory()->ictDirector()->create()->can('publish', $this->result->fresh()))->toBeFalse();
    });

    it('does not collapse into approval', function () {
        // §16: "letting approve publish in one step" breaks the defence.
        $this->result->update(['status' => ResultStatus::Submitted]);
        $officer = User::factory()->examOfficer()->create();

        expect($officer->can('approve', $this->result->fresh()))->toBeTrue()
            ->and($officer->can('publish', $this->result->fresh()))->toBeFalse();
    });
});

describe('reading a result', function () {
    it('lets a student read only their own, and only once published', function () {
        $owner = $this->registration->student->user;
        $stranger = User::factory()->student()->create();

        expect($owner->can('view', $this->result))->toBeFalse('draft results are not visible');

        $this->result->update(['status' => ResultStatus::Published]);

        expect($owner->can('view', $this->result->fresh()))->toBeTrue()
            ->and($stranger->can('view', $this->result->fresh()))->toBeFalse();
    });

    it('hides an approved but unpublished result from its own student', function () {
        $owner = $this->registration->student->user;
        $this->result->update(['status' => ResultStatus::Approved]);

        expect($owner->can('view', $this->result->fresh()))->toBeFalse();
    });
});

it('never allows deletion through the application', function (string $state) {
    expect(User::factory()->{$state}()->create()->can('delete', $this->result))->toBeFalse();
})->with(['admin', 'examOfficer', 'ictDirector', 'lecturer', 'student']);
