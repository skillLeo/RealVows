<?php

namespace App\Mail;

use App\Models\Guardian;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A second and final nudge to somebody who has not answered.
 *
 * ⚠️ IT DOES NOT EXTEND MemberMailable, for the same reason the invitation
 * does not: this recipient may still have no account, so there is no User to
 * build a signed unsubscribe link from.
 *
 * ⚠️ IT SAYS HOW TO STOP WITHOUT A LINK. "Ignore this and nothing else
 * arrives" is true — the schedule stops at two reminders — and it is a more
 * honest instruction than an unsubscribe link that implies a subscription
 * nobody agreed to.
 */
class TrustedContactReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Guardian $guardian,
        public readonly string $token,
        public readonly bool $isFinal,
    ) {}

    public function envelope(): Envelope
    {
        $first = $this->guardian->member?->profile?->first_name ?? 'A member';

        return new Envelope(
            subject: $this->isFinal
                ? "A last reminder about {$first}"
                : "{$first} is still waiting on you",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trusted-contact-reminder',
            text: 'emails.text.trusted-contact-reminder',
            with: [
                'heading' => $this->isFinal ? 'One last reminder' : 'A quick reminder',
                'preheader' => 'Somebody asked you to confirm they are who they say they are.',
                'memberName' => $this->guardian->member?->profile?->first_name ?? 'A member',
                'acceptUrl' => route('trusted-contact.accept', ['token' => $this->token]),
                'isFinal' => $this->isFinal,
            ],
        );
    }
}
