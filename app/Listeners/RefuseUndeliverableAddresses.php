<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

/**
 * ⚠️ NEVER ATTEMPT DELIVERY TO AN ADDRESS THAT CANNOT POSSIBLY RECEIVE.
 *
 * ── What this was costing ─────────────────────────────────────────────────
 *
 * The demo members hold `@realvows.test` addresses, and `.test` is reserved by
 * RFC 6761 — it does not resolve anywhere, by design, forever. The daily
 * trusted-contact reminder chases five of them, so every morning five messages
 * went out, failed, and returned as bounces into info@realvows.com.
 *
 * Ten of them were sitting in that inbox when the client opened it.
 *
 * ⚠️ THE REAL COST IS REPUTATION, NOT NOISE. A brand-new sending domain is
 * judged on its first few hundred messages, and a steady trickle of hard
 * bounces is the single clearest signal to a receiving server that a sender is
 * careless. The domain that pays for that is the one carrying every real
 * introduction later.
 *
 * ── ⚠️ WHY A LISTENER AND NOT A CHECK AT EACH SEND SITE ───────────────────
 *
 * There are a dozen places this application sends mail and there will be more.
 * A guard added to each of them is a guard somebody forgets on the thirteenth.
 * `MessageSending` is the one point every message passes through, and returning
 * false there cancels the send.
 *
 * ⚠️ IT REFUSES, IT DOES NOT REWRITE. Silently redirecting a message somewhere
 * else would be worse than sending it: the platform would believe a member had
 * been told something they never received.
 */
class RefuseUndeliverableAddresses
{
    /**
     * Reserved by RFC 6761 and RFC 2606. These are guaranteed never to resolve,
     * which is exactly why they are used for demo data.
     *
     * ⚠️ ONLY THE GUARANTEED ONES. A list that also refused typos would be
     * making a judgement about somebody's real address, and being wrong about
     * that means a member silently never hears from us — the failure this
     * whole file exists to prevent, pointed the other way.
     */
    private const NEVER_RESOLVES = ['.test', '.invalid', '.example', '.localhost'];

    public function handle(MessageSending $event): bool
    {
        $refused = [];

        foreach (['To', 'Cc', 'Bcc'] as $header) {
            foreach ($event->message->getHeaders()->all($header) as $line) {
                foreach ($line->getAddresses() as $address) {
                    if ($this->cannotResolve($address->getAddress())) {
                        $refused[] = $address->getAddress();
                    }
                }
            }
        }

        if ($refused === []) {
            return true;
        }

        /*
         * ⚠️ RECORDED, NOT SWALLOWED. This is the correct outcome and it is
         * still worth knowing about: if it ever fires for a real member, the
         * cause is a bad address on a real account rather than demo data, and
         * that is somebody unable to receive anything at all.
         */
        Log::info('Mail refused: the address can never resolve.', [
            'addresses' => $refused,
            'subject' => $event->message->getSubject(),
            'why' => 'Reserved by RFC 6761. Sending would hard-bounce and cost sending '
                .'reputation on a domain that has none to spare yet.',
        ]);

        return false;
    }

    private function cannotResolve(string $address): bool
    {
        $address = strtolower(trim($address));

        foreach (self::NEVER_RESOLVES as $suffix) {
            if (str_ends_with($address, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
