<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * An alert to whoever runs this platform. Never to a member.
 *
 * ⚠️ THIS IS A MAILABLE RATHER THAN `Mail::raw` FOR ONE REASON: raw CANNOT BE
 * TESTED.
 *
 * `MailFake::raw()` is an empty method. It records nothing, so under
 * `Mail::fake()` a raw send is indistinguishable from no send at all, and any
 * test asserting the alert went out would be asserting nothing whatsoever.
 *
 * That matters most for the credit ledger check, which is the money alarm. An
 * alert nobody can prove is sent is the same family of fault as an alert nobody
 * reads: the difference between a system that tells you and one that only
 * appears to.
 *
 * ⚠️ IT IS NOT QUEUED, DELIBERATELY. Half of what these alerts report is the
 * queue itself failing. An alert that needs a working queue in order to say
 * "the queue is broken" arrives never.
 */
class OperationalAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, list<string>>  $sections
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $intro,
        public readonly array $sections,
        public readonly string $outro = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        // Text only. See the note in the view.
        return new Content(text: 'emails.operational-alert', with: [
            'intro' => $this->intro,
            'sections' => $this->sections,
            'outro' => $this->outro,
        ]);
    }
}
