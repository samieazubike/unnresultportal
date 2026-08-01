<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;

/**
 * Guards the student record and everything hanging off it — results,
 * dashboards, transcripts.
 *
 * README §3: "A student must never be able to read another student's result
 * by changing an ID in the URL." That rule is enforced here, once, and every
 * student-scoped controller authorises against it.
 */
class StudentPolicy
{
    public function view(User $user, Student $student): bool
    {
        if ($user->isStudent()) {
            return $student->user_id === $user->getKey();
        }

        if ($user->isLecturer()) {
            // A lecturer sees a student only through a course they teach.
            return $student->registrations()
                ->whereHas('course', fn ($query) => $query->whereHas(
                    'lecturers',
                    fn ($lecturers) => $lecturers->whereKey($user->getKey()),
                ))
                ->exists();
        }

        return $user->hasRole(UserRole::ExamOfficer, UserRole::IctDirector, UserRole::Admin);
    }

    /**
     * Download the transcript. Students may take their own, and alumni keep
     * that access indefinitely (§3).
     */
    public function downloadTranscript(User $user, Student $student): bool
    {
        return $this->view($user, $student);
    }

    /**
     * See the generated academic recommendation (§8).
     */
    public function viewRecommendation(User $user, Student $student): bool
    {
        return $this->view($user, $student);
    }

    public function manage(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
