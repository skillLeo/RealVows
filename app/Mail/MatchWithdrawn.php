<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent only to somebody who PAID for an introduction we have since withdrawn.
 *
 * ⚠️ It never says why. The other person may have been suspended, may have
 * failed a re-check, or may have left — and none of that is this member's
 * business. What they are owed is the fact and their credit back.
 */
class MatchWithdrawn extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ?string $firstName,
        public ?string $theirName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'One of your introductions has been withdrawn');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.match-withdrawn');
    }
}
