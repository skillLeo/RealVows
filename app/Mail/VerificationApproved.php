<?php

namespace App\Mail;

use App\Models\Verification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationApproved extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Verification $verification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You're verified — welcome to RealVows");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-approved',
            with: ['firstName' => $this->verification->profile?->first_name],
        );
    }
}
