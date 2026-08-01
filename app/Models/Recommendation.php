<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The cached advice for one student in one session, stored alongside the
 * facts and flags it was generated from so every sentence stays traceable
 * back to a query (README §8).
 *
 * @property int $id
 * @property int $student_id
 * @property string $session
 * @property array<string, mixed> $facts
 * @property array<int, string> $flags
 * @property string $body
 * @property string $source
 * @property Carbon $generated_at
 * @property-read Student $student
 */
#[Fillable(['student_id', 'session', 'facts', 'flags', 'body', 'source', 'generated_at'])]
class Recommendation extends Model
{
    public const SOURCE_LLM = 'llm';

    public const SOURCE_FALLBACK = 'fallback';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'facts' => 'array',
            'flags' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
