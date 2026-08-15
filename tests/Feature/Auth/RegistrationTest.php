<?php

use App\Enums\UserRole;
use App\Http\Controllers\Auth\AccountClaimController;
use App\Mail\StudentClaimCodeMail;
use App\Models\AccountClaim;
use App\Models\Student;
use App\Models\User;
use App\Services\AccountClaimService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

/**
 * Registration is a claim, not a signup: the student already exists on the
 * roster and is proving they are that row.
 */
beforeEach(function () {
    Mail::fake();

    foreach (['claim-lookup', 'claim-code', 'claim-resend'] as $limiter) {
        RateLimiter::clear($limiter);
    }
});

/**
 * Start a claim and hand back the code that was mailed. The plaintext exists
 * nowhere but the mailable — the database only ever sees its hash.
 */
function startClaim(Student $student, string $email = 'ada@example.com'): string
{
    test()->post(route('register.store'), [
        'reg_number' => $student->reg_number,
        'email' => $email,
    ]);

    $code = null;

    Mail::assertSent(StudentClaimCodeMail::class, function (StudentClaimCodeMail $mail) use ($email, &$code) {
        $code = $mail->code;

        return $mail->hasTo($email);
    });

    return (string) $code;
}

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('auth/register'));
});

test('fortify self-registration is switched off', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse()
        ->and(Route::getRoutes()->getByName('register')?->getActionName())
        ->toContain(AccountClaimController::class);
});

test('a student claims their roster account end to end', function () {
    $student = Student::factory()->unclaimed()->create([
        'reg_number' => '2021/000123',
        'name' => 'Ada Lovelace',
    ]);

    $this->post(route('register.store'), [
        'reg_number' => '2021/000123',
        'email' => 'ada@example.com',
    ])->assertRedirect(route('register.verify'));

    $code = null;
    Mail::assertSent(StudentClaimCodeMail::class, function (StudentClaimCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return $mail->hasTo('ada@example.com') && $mail->name === 'Ada Lovelace';
    });

    $this->assertDatabaseHas('account_claims', [
        'student_id' => $student->id,
        'email' => 'ada@example.com',
    ]);

    $this->get(route('register.verify'))->assertInertia(fn (Assert $page) => $page
        ->component('auth/register-verify')
        ->where('regNumber', '2021/000123')
        ->where('email', 'a••@example.com')
    );

    $this->post(route('register.verify.store'), ['code' => $code])
        ->assertRedirect(route('register.password'));

    $this->get(route('register.password'))->assertInertia(fn (Assert $page) => $page
        ->component('auth/register-password')
        ->where('name', 'Ada Lovelace')
    );

    $this->post(route('register.password.store'), [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('student.dashboard'));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'ada@example.com')->sole();

    expect($user->name)->toBe('Ada Lovelace')
        ->and($user->role)->toBe(UserRole::Student)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($student->fresh()->user_id)->toBe($user->id)
        ->and(AccountClaim::query()->sole()->consumed_at)->not->toBeNull();
});

test('an unknown registration number is refused without mailing anyone', function () {
    $response = $this->post(route('register.store'), [
        'reg_number' => '2021/999999',
        'email' => 'nobody@example.com',
    ]);

    $response->assertSessionHasErrors('reg_number');
    Mail::assertNothingSent();
    $this->assertGuest();
});

test('an already claimed registration number is refused indistinguishably', function () {
    $student = Student::factory()->create(['reg_number' => '2021/000123']);

    // One wording for both cases. Telling them apart would turn this form
    // into a way of enumerating which students have already registered.
    $expected = 'We could not find a student record waiting to be claimed for that registration number. Check the number, or ask your department to add you.';

    foreach ([$student->reg_number, '2021/999999'] as $regNumber) {
        $this->post(route('register.store'), [
            'reg_number' => $regNumber,
            'email' => 'someone@example.com',
        ])->assertSessionHasErrors(['reg_number' => $expected]);
    }

    Mail::assertNothingSent();
});

test('an email already in use is rejected', function () {
    $student = Student::factory()->unclaimed()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('register.store'), [
        'reg_number' => $student->reg_number,
        'email' => 'taken@example.com',
    ])->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

test('a registration number is matched despite case and stray spaces', function () {
    $student = Student::factory()->unclaimed()->create(['reg_number' => '2021/000123']);

    $this->post(route('register.store'), [
        'reg_number' => ' 2021/000123 ',
        'email' => 'ada@example.com',
    ])->assertRedirect(route('register.verify'));

    $this->assertDatabaseHas('account_claims', ['student_id' => $student->id]);
});

test('a wrong code is refused and spends an attempt', function () {
    $student = Student::factory()->unclaimed()->create();
    startClaim($student);

    $this->post(route('register.verify.store'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect(AccountClaim::query()->sole()->attempts)->toBe(1);
    $this->assertGuest();
});

test('an expired code sends the student back to the start', function () {
    $student = Student::factory()->unclaimed()->create();
    $code = startClaim($student);

    $this->travel(AccountClaimService::EXPIRES_MINUTES + 1)->minutes();

    $this->post(route('register.verify.store'), ['code' => $code])
        ->assertRedirect(route('register'))
        ->assertSessionHas('error');

    $this->assertGuest();
});

test('the last wrong attempt burns the claim', function () {
    $student = Student::factory()->unclaimed()->create();
    startClaim($student);

    foreach (range(1, AccountClaimService::MAX_ATTEMPTS - 1) as $ignored) {
        $this->post(route('register.verify.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    expect(AccountClaim::query()->sole()->consumed_at)->toBeNull();

    $this->post(route('register.verify.store'), ['code' => '000000'])
        ->assertRedirect(route('register'));

    expect(AccountClaim::query()->sole()->consumed_at)->not->toBeNull();
});

test('resending issues a new code and retires the old one', function () {
    $student = Student::factory()->unclaimed()->create();
    $first = startClaim($student);

    $this->post(route('register.verify.resend'))->assertSessionHas('status');

    $codes = [];
    Mail::assertSent(StudentClaimCodeMail::class, function (StudentClaimCodeMail $mail) use (&$codes) {
        $codes[] = $mail->code;

        return true;
    });

    $second = collect($codes)->last();

    expect($codes)->toHaveCount(2)->and($second)->not->toBe($first);

    // The first code now belongs to a retired claim and is worthless.
    $this->post(route('register.verify.store'), ['code' => $first])
        ->assertSessionHasErrors('code');

    $this->post(route('register.verify.store'), ['code' => $second])
        ->assertRedirect(route('register.password'));
});

test('the verify and password steps refuse to render without a live claim', function () {
    $this->get(route('register.verify'))->assertRedirect(route('register'));
    $this->get(route('register.password'))->assertRedirect(route('register'));
});

test('the password step refuses a claim whose code has not been entered', function () {
    $student = Student::factory()->unclaimed()->create();
    startClaim($student);

    $this->get(route('register.password'))->assertRedirect(route('register.verify'));

    $this->post(route('register.password.store'), [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('register.verify'));

    $this->assertGuest();
});

test('the lookup step is throttled', function () {
    $student = Student::factory()->unclaimed()->create();

    foreach (range(1, 5) as $ignored) {
        $this->post(route('register.store'), [
            'reg_number' => $student->reg_number,
            'email' => 'ada@example.com',
        ]);
    }

    $this->post(route('register.store'), [
        'reg_number' => $student->reg_number,
        'email' => 'ada@example.com',
    ])->assertStatus(429);
});

test('the code step is throttled', function () {
    $student = Student::factory()->unclaimed()->create();
    startClaim($student);

    // Malformed codes are rejected by validation before an attempt is spent,
    // so this measures the limiter rather than the attempt ceiling.
    foreach (range(1, 10) as $ignored) {
        $this->post(route('register.verify.store'), ['code' => 'abcdef'])
            ->assertSessionHasErrors('code');
    }

    $this->post(route('register.verify.store'), ['code' => 'abcdef'])->assertStatus(429);
});

test('an authenticated user cannot reach the registration flow', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('register'))
        ->assertRedirect(route('dashboard'));
});
