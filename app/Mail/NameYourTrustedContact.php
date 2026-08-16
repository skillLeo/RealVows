<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * ⚠️ SHE ACCEPTED AN INTRODUCTION AND HAS NOT TOLD US WHO SHE TRUSTS.
 *
 * The trusted contact is asked at acceptance now rather than at signup, so a
 * member can say yes and stop there. Her introduction stays accepted and
 * waiting — she acted, she is not ignoring it — and nothing can move until the
 * question is answered.
 *
 * ⚠️ TWICE, THEN THE PLATFORM STOPS. Day three and day seven. A prompt that
 * keeps arriving is a platform arguing with a decision she is entitled to make
 * by doing nothing, and this one is about her family.
 *
 * ⚠️ IT NEVER MENTIONS THE OTHER PERSON. Naming him, or describing him, turns a
 * reminder into pressure and hands her a detail the gate has not released.
 */
class NameYourTrustedContact extends MemberMailable
{
    public function __construct(public User $recipient, public int $daysWaiting)
    {
        parent::__construct($recipient);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tell us who you trust');
    }

    protected function textView(): string
    {
        return 'emails.text.name-your-trusted-contact';
    }

    /** @return array<string, mixed> */
    protected function payload(): array
    {
        return ['daysWaiting' => $this->daysWaiting];
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.name-your-trusted-contact',
            text: $this->textView(),
            with: $this->commonData(),
        );
    }
}
