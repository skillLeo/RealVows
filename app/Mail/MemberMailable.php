<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Everything a member receives that is not a receipt.
 *
 * ⚠️ THE UNSUBSCRIBE LINK IS BUILT HERE, ONCE, RATHER THAN IN SEVEN TEMPLATES.
 *
 * A missing unsubscribe link is a spam complaint, and a spam complaint on a
 * brand-new sending domain costs the reputation of every message that follows —
 * including receipts, which members are entitled to. Building it per template
 * means six chances to forget; building it here means none.
 *
 * ⚠️ THE LINK IS SIGNED AND NEVER EXPIRES. DO NOT "HARDEN" THIS.
 *
 * Every other signed URL in this product is short-lived, so this one reads as
 * an oversight and the fix looks like a one-line improvement: add an expiry,
 * match the receipt, done.
 *
 * It is not an oversight. People unsubscribe from OLD email — usually the
 * message that finally annoyed them, which may be weeks down the inbox. An
 * expiring link sends exactly that person to an error page, and their next
 * move is the spam button.
 *
 * Weigh the two failures honestly. A leaked unsubscribe link lets somebody
 * switch off one channel for one member, who can switch it back on from their
 * account in a click. A spam complaint damages the sending reputation of the
 * whole domain — every message including receipts — and takes weeks to repair.
 * An expiry guards the cheaper failure by causing the more expensive one.
 */
abstract class MemberMailable extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public User $recipient) {}

    /**
     * ⚠️ EVERY MEMBER EMAIL MUST HAVE A PLAIN-TEXT PART, AND IT IS NOT AN
     * ACCESSIBILITY NICETY.
     *
     * A multipart message with no text alternative scores measurably worse
     * with spam filters. On an established domain that is survivable; on a
     * brand-new one with no reputation to spend, it is the difference between
     * the inbox and the junk folder for the first few hundred sends — which are
     * precisely the sends that establish the reputation.
     *
     * It is also what renders in a smart watch notification and in the preview
     * line of most clients.
     *
     * ⚠️ WRITTEN AS PROSE, NOT AS STRIPPED HTML. A text part produced by
     * removing tags reads as debris — orphan link text, doubled headings,
     * "Open your dashboard" with no URL beside it — and a filter can tell.
     */
    abstract protected function textView(): string;

    /** @return array<string, mixed> */
    abstract protected function payload(): array;

    protected function commonData(): array
    {
        return array_merge($this->payload(), [
            'recipient' => $this->recipient,
            // Normalised, not read raw: a whitespace-only name is truthy and
            // every greeting in the product is a ternary on truthiness.
            'firstName' => $this->recipient->profile?->greetingName(),
            'unsubscribeUrl' => URL::signedRoute('unsubscribe', [
                'user' => $this->recipient->id,
                'channel' => 'email',
            ]),
        ]);
    }
}
