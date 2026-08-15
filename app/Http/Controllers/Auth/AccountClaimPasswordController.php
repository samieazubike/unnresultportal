<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\ResolvesAccountClaims;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ClaimPasswordRequest;
use App\Services\AccountClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Step three: set a password, and the roster row becomes an account.
 */
class AccountClaimPasswordController extends Controller
{
    use ResolvesAccountClaims;

    public function __construct(private readonly AccountClaimService $claims) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $claim = $this->currentClaim($request);

        if ($claim === null) {
            return to_route('register')->with('error', 'That verification code has expired. Start again to get a new one.');
        }

        if (! $claim->isVerified()) {
            return to_route('register.verify');
        }

        return Inertia::render('auth/register-password', [
            'name' => $claim->student->name,
            'regNumber' => $claim->student->reg_number,
            'email' => $claim->email,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(ClaimPasswordRequest $request): RedirectResponse
    {
        $claim = $this->currentClaim($request);

        if ($claim === null) {
            return to_route('register')->with('error', 'That verification code has expired. Start again to get a new one.');
        }

        if (! $claim->isVerified()) {
            return to_route('register.verify');
        }

        try {
            $user = $this->claims->claim($claim, $request->string('password')->toString());
        } catch (RuntimeException $exception) {
            // Someone else finished claiming this row in the meantime.
            $this->forgetClaim($request);

            return to_route('register')->with('error', $exception->getMessage());
        }

        $this->forgetClaim($request);

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('student.dashboard');
    }
}
