<?php

namespace App\Support;

use App\Models\Result;

/**
 * Tamper-evidence for stored results.
 *
 * The threat is an actor with database access — the ICT director is the named
 * case — altering a score after it has been approved. Encryption does not
 * address that: it stops someone reading a row, not overwriting it. What
 * detects it is a signature they cannot recompute.
 *
 * Every authorised write recomputes an HMAC-SHA256 over the canonical score
 * payload, keyed by the application key. Any change made outside the
 * application leaves a row whose stored hash no longer matches its contents,
 * and the mismatch is caught on the next read.
 */
final class ResultIntegrity
{
    public const ALGO = 'hmac-sha256';

    /**
     * Compute the signature for a result's current score payload.
     *
     * The serialisation is canonical — fixed key order, fixed numeric
     * formatting — so that an unchanged row always hashes identically
     * regardless of how it was loaded.
     */
    public static function hash(Result $result): string
    {
        return hash_hmac('sha256', self::canonicalPayload($result), self::key());
    }

    /**
     * Whether a result's stored signature still matches its contents.
     *
     * A result that has never been signed is treated as intact: signing
     * begins at approval, and an unsigned draft has nothing to contradict.
     */
    public static function verify(Result $result): bool
    {
        $signature = $result->signature;

        if ($signature === null) {
            return true;
        }

        return hash_equals($signature->integrity_hash, self::hash($result));
    }

    /**
     * The exact bytes that get signed. Kept private and deterministic; any
     * change to this format invalidates every existing signature.
     */
    private static function canonicalPayload(Result $result): string
    {
        return implode('|', [
            'registration_id='.$result->registration_id,
            'ca_score='.self::number($result->ca_score),
            'exam_score='.self::number($result->exam_score),
            'total_score='.self::number($result->total_score),
            'grade='.($result->grade ?? ''),
            'grade_point='.($result->grade_point ?? ''),
        ]);
    }

    private static function number(?float $value): string
    {
        return $value === null ? '' : number_format($value, 2, '.', '');
    }

    private static function key(): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), strict: true) ?: $key;
        }

        return $key;
    }
}
