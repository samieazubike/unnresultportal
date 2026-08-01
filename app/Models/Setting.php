<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value settings, per README §7. Currently just the failure-rate
 * threshold, which an administrator may change (§3).
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const FAILURE_THRESHOLD = 'failure_rate_threshold';

    private const CACHE_PREFIX = 'setting:';

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(
            self::CACHE_PREFIX.$key,
            fn () => self::query()->where('key', $key)->value('value') ?? $default,
        );
    }

    public static function put(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * The failure rate above which a course is flagged, as a percentage.
     * Defaults to 40 per README §7.
     */
    public static function failureThreshold(): float
    {
        return (float) self::get(
            self::FAILURE_THRESHOLD,
            config('grading.failure_threshold', 40),
        );
    }
}
