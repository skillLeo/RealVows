<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MutualInterest extends MemberMailable
{
    public function __construct(public User $recipient, public string $otherFirstName)
    {
        parent::__construct($recipient);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You are both interested');
    }

    protected function textView(): string
    {
        return 'emails.text.mutual-interest';
    }

    /** @return array<string, mixed> */
    protected function payload(): array
    {
        return [
            'otherFirstName' => $this->otherFirstName,
        ];
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mutual-interest',
            text: $this->textView(),
            with: $this->commonData(),
        );
    }
}
