<?php

namespace App\Models;

use Database\Factories\CourseOfferingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One course an administrator has pre-selected for a department and level in
 * a session and semester.
 *
 * @property int $id
 * @property int $dept_id
 * @property int $level
 * @property string $session
 * @property int $semester
 * @property int $course_id
 * @property-read Department $department
 * @property-read Course $course
 */
#[Fillable(['dept_id', 'level', 'session', 'semester', 'course_id'])]
class CourseOffering extends Model
{
    /** @use HasFactory<CourseOfferingFactory> */
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
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
