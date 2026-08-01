<?php

namespace App\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $course_code
 * @property string $course_title
 * @property int $credit_units
 * @property int $dept_id
 * @property int|null $lecturer_id
 * @property-read Department $department
 * @property-read User|null $head
 * @property-read Collection<int, User> $lecturers
 * @property-read Collection<int, Registration> $registrations
 */
#[Fillable(['course_code', 'course_title', 'credit_units', 'dept_id', 'lecturer_id'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'dept_id');
    }

    /**
     * The course head, who alone may submit the sheet for review.
     *
     * @return BelongsTo<User, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    /**
     * The full teaching team, head included. Any member may enter and correct
     * scores; the database guarantees the head is among them.
     *
     * @return BelongsToMany<User, $this>
     */
    public function lecturers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_lecturer', 'course_id', 'lecturer_id')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function isTaughtBy(User $user): bool
    {
        return $this->lecturers()->whereKey($user->getKey())->exists();
    }

    public function isHeadedBy(User $user): bool
    {
        return $this->lecturer_id !== null && $this->lecturer_id === $user->getKey();
    }
}
