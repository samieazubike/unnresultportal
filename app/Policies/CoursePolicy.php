<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isStudent();
    }

    public function view(User $user, Course $course): bool
    {
        if ($user->isLecturer()) {
            return $course->isTaughtBy($user);
        }

        return $user->hasRole(UserRole::ExamOfficer, UserRole::IctDirector, UserRole::Admin);
    }

    /**
     * Open the score sheet for a course. Any member of the teaching team may;
     * whether an individual row can then be written is decided by
     * ResultPolicy::update().
     */
    public function enterScores(User $user, Course $course): bool
    {
        return $user->isLecturer() && $course->isTaughtBy($user);
    }

    /**
     * Submit the whole sheet for review — the head's responsibility alone.
     */
    public function submitScores(User $user, Course $course): bool
    {
        return $user->isLecturer() && $course->isHeadedBy($user);
    }

    /**
     * Course records, and lecturer assignment, belong to the administrator
     * (§3). Note this covers assignment only, never scores.
     */
    public function manage(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Course $course): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->manage($user);
    }
}
