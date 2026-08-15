<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One in-progress attempt to claim a roster row.
 *
 * The verification code is never stored, only its hash. Everything else —
 * the email offered, the originating IP, the attempt count — is kept even for
 * abandoned and failed claims, because that record is what lets an
 * administrator see who has been probing registration numbers.
 *
 * @property int $id
 * @property int $student_id
 * @property string $email
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property int $attempts
 * @property Carbon|null $verified_at
 * @property Carbon|null $consumed_at
 * @property string|null $ip_address
 * @property-read Student $student
 */
#[Fillable(['student_id', 'email', 'code_hash', 'expires_at', 'attempts', 'verified_at', 'consumed_at', 'ip_address'])]
class AccountClaim extends Model
{
    use MassPrunable;

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Still usable: not spent, not expired.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * A day is long past any claim's usefulness, and the audit value of a
     * failed attempt does not outlast the attacker's session.
     *
     * @return Builder<$this>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDay());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
