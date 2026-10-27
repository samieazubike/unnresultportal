<?php

namespace App\Models;

use Database\Factories\RegistrationWindowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Whether students may change their course registration for a session and
 * semester. Opened and closed by an administrator; no row means closed.
 *
 * @property int $id
 * @property string $session
 * @property int $semester
 * @property bool $is_open
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $updater
 */
#[Fillable(['session', 'semester', 'is_open', 'updated_by'])]
class RegistrationWindow extends Model
{
    /** @use HasFactory<RegistrationWindowFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'is_open' => 'boolean',
        ];
    }

    public static function isOpen(string $session, int $semester): bool
    {
        return self::query()
            ->where('session', $session)
            ->where('semester', $semester)
            ->where('is_open', true)
            ->exists();
    }

    /**
     * The most recently opened window still open, if any.
     */
    public static function current(): ?self
    {
        return self::query()->where('is_open', true)->latest('updated_at')->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
