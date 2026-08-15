<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\ResolvesAccountClaims;
use App\Http\Controllers\Controller;
use App\Mail\StudentClaimCodeMail;
use App\Services\AccountClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step two: the emailed code.
 *
 * This is the only proof that the person holding the registration number also
 * holds the mailbox they want the account attached to.
 */
class AccountClaimCodeController extends Controller
{
    use ResolvesAccountClaims;

    private const EXPIRED = 'That verification code has expired. Start again to get a new one.';

    public function __construct(private readonly AccountClaimService $claims) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $claim = $this->currentClaim($request);

        if ($claim === null) {
            return to_route('register')->with('error', self::EXPIRED);
        }

        return Inertia::render('auth/register-verify', [
            'email' => $this->maskEmail($claim->email),
            'regNumber' => $claim->student->reg_number,
            'expiresInMinutes' => AccountClaimService::EXPIRES_MINUTES,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $claim = $this->currentClaim($request);

        if ($claim === null) {
            return to_route('register')->with('error', self::EXPIRED);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        if (! $this->claims->verify($claim, $validated['code'])) {
            // A spent claim means the attempt ceiling was reached; there is
            // nothing left to retry against, so send them back to the start.
            if ($claim->fresh()?->consumed_at !== null) {
                $this->forgetClaim($request);

                return to_route('register')->with('error', 'Too many incorrect codes. Start again to get a new one.');
            }

            return back()->withErrors(['code' => 'That code is not correct.']);
        }

        return to_route('register.password');
    }

    public function resend(Request $request): RedirectResponse
    {
        $claim = $this->currentClaim($request);

        if ($claim === null) {
            return to_route('register')->with('error', self::EXPIRED);
        }

        $student = $claim->student;

        ['claim' => $replacement, 'code' => $code] = $this->claims->issue($student, $claim->email, $request->ip());

        Mail::to($claim->email)->send(new StudentClaimCodeMail($student->name, $code));

        $this->rememberClaim($request, $replacement);

        return back()->with('status', 'A new code is on its way.');
    }
}
