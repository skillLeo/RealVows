<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class IntroductionsUnlocked extends MemberMailable
{
    public function __construct(public User $recipient, public int $count)
    {
        parent::__construct($recipient);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your introductions are open');
    }

    protected function textView(): string
    {
        return 'emails.text.introductions-unlocked';
    }

    /** @return array<string, mixed> */
    protected function payload(): array
    {
        return [
            'count' => $this->count,
        ];
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.introductions-unlocked',
            text: $this->textView(),
            with: $this->commonData(),
        );
    }
}
