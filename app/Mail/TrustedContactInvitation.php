<?php

namespace App\Mail;

use App\Models\Guardian;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one email a trusted contact receives before they have an account.
 *
 * ⚠️ IT DOES NOT EXTEND MemberMailable, AND THAT IS DELIBERATE.
 *
 * MemberMailable builds a signed unsubscribe link from a User. The recipient
 * here has no account yet — that is the entire point of the message — so there
 * is nothing to sign and nothing to unsubscribe from. Inheriting it would mean
 * either a broken link or a link that quietly identifies somebody who has not
 * agreed to be identified.
 *
 * ⚠️ IT IS NOT MARKETING AND CARRIES NO OPT-OUT FOR THAT REASON. It is a
 * one-off transactional message somebody's daughter asked us to send. If they
 * do nothing, nothing else arrives: the reminder schedule in B8 stops at 14
 * days and the record is replaced rather than chased.
 *
 * ⚠️ IT NAMES THE MEMBER'S FIRST NAME ONLY. The recipient is expected to know
 * her, and if we have the wrong address a surname plus a platform is enough to
 * identify her to a stranger.
 */
class TrustedContactInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Guardian $guardian,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        $first = $this->guardian->member?->profile?->first_name ?? 'A member';

        return new Envelope(
            subject: "{$first} has asked you to be her trusted contact on RealVows",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: null,
            view: 'emails.trusted-contact-invitation',
            text: 'emails.text.trusted-contact-invitation',
            with: [
                'heading' => 'You have been named as a trusted contact',
                'preheader' => 'Someone has asked you to confirm they are who they say they are.',
                'memberName' => $this->guardian->member?->profile?->first_name ?? 'A member',
                'relationship' => Guardian::RELATIONSHIPS[$this->guardian->relationship] ?? 'family',
                'acceptUrl' => route('trusted-contact.accept', ['token' => $this->token]),
                'expiresInDays' => \App\Models\GuardianInvitation::LIFETIME_DAYS,
            ],
        );
    }
}
