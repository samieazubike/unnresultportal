<?php

namespace App\Models;

use App\Enums\ResultStatus;
use Database\Factories\ResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $registration_id
 * @property float|null $ca_score
 * @property float|null $exam_score
 * @property float|null $total_score
 * @property string|null $grade
 * @property int|null $grade_point
 * @property ResultStatus $status
 * @property int|null $published_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Registration $registration
 * @property-read User|null $publisher
 * @property-read ResultSignature|null $signature
 * @property-read Collection<int, ResultAudit> $audits
 */
#[Fillable(['registration_id', 'ca_score', 'exam_score', 'total_score', 'grade', 'grade_point', 'status', 'published_by'])]
class Result extends Model
{
    /** @use HasFactory<ResultFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ca_score' => 'float',
            'exam_score' => 'float',
            'total_score' => 'float',
            'grade_point' => 'integer',
            'status' => ResultStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @return HasOne<ResultSignature, $this>
     */
    public function signature(): HasOne
    {
        return $this->hasOne(ResultSignature::class);
    }

    /**
     * @return HasMany<ResultAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(ResultAudit::class)->latest('created_at');
    }

    /**
     * Only published results count toward GPA, CGPA and every chart (§9).
     *
     * @param  Builder<$this>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ResultStatus::Published);
    }

    public function isPublished(): bool
    {
        return $this->status === ResultStatus::Published;
    }

    /**
     * Whether the scores may still be written. False from Approved onward,
     * for everyone including the lecturer who entered them.
     */
    public function allowsScoreEditing(): bool
    {
        return $this->status->allowsScoreEditing();
    }

    public function hasScores(): bool
    {
        return $this->ca_score !== null && $this->exam_score !== null;
    }
}
