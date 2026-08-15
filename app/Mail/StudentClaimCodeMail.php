<?php

namespace App\Mail;

use App\Services\AccountClaimService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The six-digit code that lets a student claim their roster row.
 *
 * Sent synchronously, not queued. It is the one message a student cannot work
 * around: if it sits in the jobs table because no worker is running, the whole
 * registration flow is dead with no visible error. Should this ever become
 * `ShouldQueue`, every `Mail::assertSent` in the test suite has to become
 * `Mail::assertQueued` in the same commit.
 *
 * `$code` is public so tests can read the plaintext back off the mailable —
 * it exists nowhere else, by design.
 */
class StudentClaimCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly int $expiresInMinutes = AccountClaimService::EXPIRES_MINUTES,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.config('app.name').' verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.student-claim-code',
        );
    }
}
