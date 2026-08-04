<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class IntroductionDelivered extends MemberMailable
{
    public function __construct(public User $recipient, public int $count, public ?int $score = null, public ?string $rationale = null, public ?string $firstNameOfMatch = null)
    {
        parent::__construct($recipient);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Someone new is waiting for you');
    }

    protected function textView(): string
    {
        return 'emails.text.introduction-delivered';
    }

    /** @return array<string, mixed> */
    protected function payload(): array
    {
        return [
            'count' => $this->count,
            'score' => $this->score,
            'rationale' => $this->rationale,
            'firstNameOfMatch' => $this->firstNameOfMatch,
        ];
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.introduction-delivered',
            text: $this->textView(),
            with: $this->commonData(),
        );
    }
}
