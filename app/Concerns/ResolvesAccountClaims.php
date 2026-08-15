<?php

namespace App\Concerns;

use App\Models\AccountClaim;
use Illuminate\Http\Request;

/**
 * Carrying a half-finished claim between the three registration steps.
 *
 * Only the claim's id lives in the session — mirroring Fortify's own
 * `login.id` — so every step re-reads the row and a page refresh behaves the
 * same as a first visit.
 */
trait ResolvesAccountClaims
{
    /**
     * The session key holding the claim being worked on.
     */
    private const CLAIM_SESSION_KEY = 'claim.id';

    /**
     * The claim in progress, or null if there is none, it has expired, or it
     * has already been spent.
     */
    protected function currentClaim(Request $request): ?AccountClaim
    {
        $id = $request->session()->get(self::CLAIM_SESSION_KEY);

        if ($id === null) {
            return null;
        }

        return AccountClaim::query()->live()->with('student')->find($id);
    }

    protected function rememberClaim(Request $request, AccountClaim $claim): void
    {
        $request->session()->put(self::CLAIM_SESSION_KEY, $claim->getKey());
    }

    protected function forgetClaim(Request $request): void
    {
        $request->session()->forget(self::CLAIM_SESSION_KEY);
    }

    /**
     * Enough of the address to recognise, not enough to harvest — the page
     * that shows it is reachable by whoever started the claim.
     */
    protected function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = mb_substr($local, 0, 1);
        $hidden = str_repeat('•', max(mb_strlen($local) - 1, 1));

        return $domain === '' ? $visible.$hidden : "{$visible}{$hidden}@{$domain}";
    }
}
