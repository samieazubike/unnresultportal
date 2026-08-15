<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\ResolvesAccountClaims;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ClaimAccountRequest;
use App\Mail\StudentClaimCodeMail;
use App\Models\Student;
use App\Services\AccountClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step one of claiming an account: registration number in, code out.
 *
 * Students are not strangers to this institution — an administrator has
 * already recorded them. Registration is therefore proving you are the person
 * on that roster row, not describing yourself from scratch.
 */
class AccountClaimController extends Controller
{
    use ResolvesAccountClaims;

    /**
     * One message for "no such registration number" and for "already
     * claimed". Telling them apart would turn this form into a way of
     * enumerating which students have signed up.
     */
    private const NOT_AVAILABLE = 'We could not find a student record waiting to be claimed for that registration number. Check the number, or ask your department to add you.';

    public function __construct(private readonly AccountClaimService $claims) {}

    public function create(): Response
    {
        return Inertia::render('auth/register');
    }

    public function store(ClaimAccountRequest $request): RedirectResponse
    {
        $student = Student::query()
            ->matchingRegNumber($request->string('reg_number')->toString())
            ->whereNull('user_id')
            ->first();

        if ($student === null) {
            return back()->withErrors(['reg_number' => self::NOT_AVAILABLE])->withInput();
        }

        $email = $request->string('email')->lower()->toString();

        ['claim' => $claim, 'code' => $code] = $this->claims->issue($student, $email, $request->ip());

        Mail::to($email)->send(new StudentClaimCodeMail($student->name, $code));

        $this->rememberClaim($request, $claim);

        return to_route('register.verify');
    }
}
