<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A row on the student roster.
 *
 * An administrator records the student first; the account comes later, when
 * the student claims it with their registration number. Until then `user_id`
 * is null, which is what `isClaimed()` reads.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $reg_number
 * @property int $dept_id
 * @property int $entry_year
 * @property-read User|null $user
 * @property-read Department $department
 * @property-read Collection<int, Registration> $registrations
 */
#[Fillable(['user_id', 'name', 'reg_number', 'dept_id', 'entry_year'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * Whether an account has been bound to this roster row. Binding happens
     * once and is never undone without an administrator.
     */
    public function isClaimed(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Match a registration number as typed.
     *
     * Case and stray whitespace are ignored, the same way the score importer
     * compares them, so "2021/000123 " off a student's phone keyboard finds
     * the row an administrator entered as "2021/000123".
     *
     * @param  Builder<$this>  $query
     */
    public function scopeMatchingRegNumber(Builder $query, string $regNumber): void
    {
        $query->whereRaw(
            "lower(replace(reg_number, ' ', '')) = ?",
            [strtolower(preg_replace('/\s+/', '', $regNumber) ?? $regNumber)],
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'dept_id');
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
