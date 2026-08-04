<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SomeoneInterested extends MemberMailable
{
    public function __construct(public User $recipient)
    {
        parent::__construct($recipient);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Someone is interested in you');
    }

    protected function textView(): string
    {
        return 'emails.text.someone-interested';
    }

    /** @return array<string, mixed> */
    protected function payload(): array
    {
        return [];
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.someone-interested',
            text: $this->textView(),
            with: $this->commonData(),
        );
    }
}
