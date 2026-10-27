<?php

namespace App\Models;

use App\Enums\RegistrationSource;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The join between a student and a course for one session and semester.
 * Each registration produces exactly one result (README §4).
 *
 * @property int $id
 * @property int $student_id
 * @property int $course_id
 * @property string $session
 * @property int $semester
 * @property RegistrationSource $source
 * @property-read Student $student
 * @property-read Course $course
 * @property-read Result|null $result
 */
#[Fillable(['student_id', 'course_id', 'session', 'semester', 'source'])]
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => RegistrationSource::class,
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasOne<Result, $this>
     */
    public function result(): HasOne
    {
        return $this->hasOne(Result::class);
    }
}
