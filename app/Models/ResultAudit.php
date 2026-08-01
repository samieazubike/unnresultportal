<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An append-only record. Nothing in the application updates or deletes these.
 *
 * @property int $id
 * @property int $result_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $from_status
 * @property string|null $to_status
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property-read Result $result
 * @property-read User|null $user
 */
#[Fillable(['result_id', 'user_id', 'action', 'from_status', 'to_status', 'old_values', 'new_values', 'ip_address', 'created_at'])]
class ResultAudit extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Result, $this>
     */
    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
