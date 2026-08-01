<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $result_id
 * @property string $integrity_hash
 * @property string $algo
 * @property bool $frozen
 * @property Carbon $signed_at
 * @property int|null $signed_by
 * @property-read Result $result
 * @property-read User|null $signer
 */
#[Fillable(['result_id', 'integrity_hash', 'algo', 'frozen', 'signed_at', 'signed_by'])]
class ResultSignature extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frozen' => 'boolean',
            'signed_at' => 'datetime',
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
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
