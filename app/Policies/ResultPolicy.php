<?php

namespace App\Policies;

use App\Enums\ResultStatus;
use App\Enums\UserRole;
use App\Models\Result;
use App\Models\User;

/**
 * Who may do what to a result.
 *
 * The central rule, and the one worth stating plainly: **only a lecturer on
 * the course's teaching team may ever write a score.** Not the administrator,
 * not the examination officer, not the ICT director. Approval and publication
 * are status-only transitions; the controllers behind them accept no score
 * fields at all, so they are structurally incapable of altering a mark.
 */
class ResultPolicy
{
    /**
     * Read a single result. Students may only ever read their own, and only
     * once it has been published (§9).
     */
    public function view(User $user, Result $result): bool
    {
        if ($user->isStudent()) {
            return $result->isPublished()
                && $result->registration->student->user_id === $user->getKey();
        }

        if ($user->isLecturer()) {
            return $result->registration->course->isTaughtBy($user);
        }

        return $user->hasRole(UserRole::ExamOfficer, UserRole::IctDirector, UserRole::Admin);
    }

    /**
     * Write CA and exam scores.
     *
     * Three conditions, all required: the actor is a lecturer, they are on
     * the teaching team of the course, and the sheet is still open for
     * editing (draft or submitted — from approved onward it is frozen for
     * everyone, including the lecturer who entered it).
     */
    public function update(User $user, Result $result): bool
    {
        if (! $user->isLecturer()) {
            return false;
        }

        if (! $result->allowsScoreEditing()) {
            return false;
        }

        return $result->registration->course->isTaughtBy($user);
    }

    /**
     * Enter scores against a course at all. Same team rule as update(),
     * expressed for a sheet that has no Result rows yet.
     */
    public function enterScores(User $user, Result $result): bool
    {
        return $this->update($user, $result);
    }

    /**
     * Send the sheet for review. Narrower than update(): any team member may
     * enter and correct scores, but only the course head signs it off.
     */
    public function submit(User $user, Result $result): bool
    {
        if (! $user->isLecturer() || $result->status !== ResultStatus::Draft) {
            return false;
        }

        return $result->registration->course->isHeadedBy($user);
    }

    /**
     * Accept a sheet academically. Does not release it to students.
     */
    public function approve(User $user, Result $result): bool
    {
        return $user->hasRole(UserRole::ExamOfficer)
            && $result->status === ResultStatus::Submitted;
    }

    /**
     * Return a sheet to the lecturer with a note.
     */
    public function reject(User $user, Result $result): bool
    {
        return $this->approve($user, $result);
    }

    /**
     * Release an approved result to the student. A separate deliberate action
     * from approval (§9), available to the examination officer and the ICT
     * director.
     */
    public function publish(User $user, Result $result): bool
    {
        return $user->canPublishResults()
            && $result->status === ResultStatus::Approved;
    }

    /**
     * Nobody deletes results through the application.
     */
    public function delete(User $user, Result $result): bool
    {
        return false;
    }
}
