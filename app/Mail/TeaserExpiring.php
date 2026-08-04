<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TeaserExpiring extends MemberMailable
{
    public function __construct(public User $recipient, public int $count, public int $daysLeft)
    {
        parent::__construct($recipient);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'An introduction is about to close');
    }

    protected function textView(): string
    {
        return 'emails.text.teaser-expiring';
    }

    /** @return array<string, mixed> */
    protected function payload(): array
    {
        return [
            'count' => $this->count,
            'daysLeft' => $this->daysLeft,
        ];
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.teaser-expiring',
            text: $this->textView(),
            with: $this->commonData(),
        );
    }
}
