<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Student|null $student
 * @property-read Collection<int, Course> $headedCourses
 * @property-read Collection<int, Course> $taughtCourses
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
        ];
    }

    /**
     * The student record belonging to this user, when the user is a student.
     *
     * @return HasOne<Student, $this>
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Courses this user heads, via courses.lecturer_id.
     *
     * @return HasMany<Course, $this>
     */
    public function headedCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'lecturer_id');
    }

    /**
     * Every course this user is on the teaching team of, head or not.
     *
     * @return BelongsToMany<Course, $this>
     */
    public function taughtCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_lecturer', 'lecturer_id', 'course_id')
            ->withTimestamps();
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, strict: true);
    }

    public function isStudent(): bool
    {
        return $this->role === UserRole::Student;
    }

    public function isLecturer(): bool
    {
        return $this->role === UserRole::Lecturer;
    }

    /**
     * Whether this user may release results to students. README §9 gives
     * publication to the examination officer and the ICT director only.
     */
    public function canPublishResults(): bool
    {
        return $this->hasRole(UserRole::ExamOfficer, UserRole::IctDirector);
    }
}
