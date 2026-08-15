<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AccountClaim;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Claiming a pre-provisioned student account.
 *
 * An administrator records the student; the student proves they hold an email
 * address and takes ownership of the row. The binding happens exactly once:
 * `students.user_id` is unique and is set under a row lock, so of two people
 * racing the same registration number, the second is refused rather than
 * colliding on the index.
 *
 * The six-digit code lives only long enough to be emailed. What is stored is
 * a bcrypt hash of it, which — together with the ten-minute expiry and the
 * five-attempt ceiling — is what keeps a 10^6 keyspace out of reach.
 */
class AccountClaimService
{
    public const EXPIRES_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /**
     * Issue a fresh code for a roster row, retiring any claim still in flight
     * for it so that only the most recent code is ever accepted.
     *
     * @return array{claim: AccountClaim, code: string} the plaintext code is
     *                                                  returned for the caller to mail and is never persisted
     */
    public function issue(Student $student, string $email, ?string $ip = null): array
    {
        $this->consumeLiveClaimsFor($student);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $claim = AccountClaim::query()->create([
            'student_id' => $student->getKey(),
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            'ip_address' => $ip,
        ]);

        return ['claim' => $claim, 'code' => $code];
    }

    /**
     * Check a submitted code, spending one attempt whether or not it matches.
     *
     * The last wrong attempt burns the claim outright: the student starts
     * again, which costs them one email and costs an attacker the whole
     * keyspace they had been working through. The `claim-code` rate limiter
     * is deliberately looser than this ceiling — the attempt count is the
     * control that matters, and it should be what a student runs into.
     */
    public function verify(AccountClaim $claim, string $code): bool
    {
        if ($claim->consumed_at !== null || $claim->isExpired()) {
            return false;
        }

        $claim->increment('attempts');

        if (! Hash::check($code, $claim->code_hash)) {
            if ($claim->attempts >= self::MAX_ATTEMPTS) {
                $claim->update(['consumed_at' => now()]);
            }

            return false;
        }

        $claim->update(['verified_at' => now()]);

        return true;
    }

    /**
     * Create the account and bind it to the roster row.
     *
     * The email address was proved in the previous step, so the account is
     * verified on creation and no second verification mail is sent.
     *
     * @throws RuntimeException if the row was claimed between the code being
     *                          verified and the password being set
     */
    public function claim(AccountClaim $claim, string $password): User
    {
        return DB::transaction(function () use ($claim, $password): User {
            $student = Student::query()
                ->lockForUpdate()
                ->findOrFail($claim->student_id);

            if ($student->isClaimed()) {
                throw new RuntimeException('That registration number has already been claimed.');
            }

            $user = User::query()->create([
                'name' => $student->name,
                'email' => $claim->email,
                'password' => $password,
                'role' => UserRole::Student,
            ]);

            // email_verified_at is deliberately not fillable.
            $user->forceFill(['email_verified_at' => now()])->save();

            $student->update(['user_id' => $user->getKey()]);
            $claim->update(['consumed_at' => now()]);

            return $user;
        });
    }

    private function consumeLiveClaimsFor(Student $student): void
    {
        AccountClaim::query()
            ->where('student_id', $student->getKey())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }
}
