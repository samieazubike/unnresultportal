<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role gate, used as role:exam_officer,ict_director.
 *
 * This is a coarse filter on who may reach a screen at all. Anything finer —
 * in particular who may write scores — is decided by the policies, which see
 * the individual result.
 */
class EnsureRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $allowed = array_map(
            fn (string $role) => UserRole::from($role),
            $roles,
        );

        abort_unless($user->hasRole(...$allowed), 403);

        return $next($request);
    }
}
