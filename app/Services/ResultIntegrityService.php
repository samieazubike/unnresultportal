<?php

namespace App\Services;

use App\Models\Result;
use App\Models\ResultSignature;
use App\Models\User;
use App\Support\ResultIntegrity;
use Illuminate\Database\Eloquent\Collection;

class ResultIntegrityService
{
    /**
     * Refresh a result's signature after an authorised write.
     *
     * A frozen signature is left alone: once the examination officer has
     * approved a sheet, the signature records what they approved, and the
     * application no longer has any legitimate reason to move it.
     */
    public function sign(Result $result, ?User $actor = null, bool $freeze = false): ResultSignature
    {
        $existing = $result->signature;

        if ($existing !== null && $existing->frozen && ! $freeze) {
            return $existing;
        }

        $signature = ResultSignature::query()->updateOrCreate(
            ['result_id' => $result->getKey()],
            [
                'integrity_hash' => ResultIntegrity::hash($result),
                'algo' => ResultIntegrity::ALGO,
                'frozen' => $freeze || ($existing?->frozen ?? false),
                'signed_at' => now(),
                'signed_by' => $actor?->getKey(),
            ],
        );

        $result->setRelation('signature', $signature);

        return $signature;
    }

    /**
     * Seal a result at approval. From here the signature is the record of
     * what was academically accepted, and any later divergence is tampering.
     */
    public function freeze(Result $result, User $approver): ResultSignature
    {
        return $this->sign($result, $approver, freeze: true);
    }

    public function verify(Result $result): bool
    {
        return ResultIntegrity::verify($result);
    }

    /**
     * Filter a set of results down to those whose signature no longer matches
     * their contents.
     *
     * @param  Collection<int, Result>  $results
     * @return Collection<int, Result>
     */
    public function tampered(Collection $results): Collection
    {
        $results->loadMissing('signature');

        return $results->reject(fn (Result $result) => $this->verify($result))->values();
    }

    /**
     * @param  Collection<int, Result>  $results
     * @return Collection<int, Result>
     */
    public function intact(Collection $results): Collection
    {
        $results->loadMissing('signature');

        return $results->filter(fn (Result $result) => $this->verify($result))->values();
    }
}
