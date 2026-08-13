<?php

namespace App\Mail;

use App\Models\Verification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationRejected extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Verification $verification,
        public string $reason,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        // Not "rejected". The member has done nothing wrong and will almost
        // always be able to fix this in two minutes.
        return new Envelope(subject: 'One more try on your RealVows photos');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-rejected',
            with: [
                'firstName' => $this->verification->profile?->greetingName(),
                'reason' => $this->reason,
                'note' => $this->note,

                /*
                 * ⚠️ `code` IS NOT PASSED, AND ITS ABSENCE IS THE FIX.
                 *
                 * Removing it from the template alone would have left a live
                 * verification code being handed to the view on every send,
                 * one @if away from returning. The rule is that this email does
                 * not carry the code, so the code does not reach the email.
                 */
            ],
        );
    }
}
