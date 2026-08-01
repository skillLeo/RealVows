<?php

namespace App\Services\Payments;

use App\Models\Purchase;

/**
 * The gateway every test uses. Deterministic, offline, and never reaches the
 * network.
 *
 * Behaviour is driven by the purchase amount so a test can ask for a
 * specific outcome without stubbing anything:
 *
 *   any amount ending 01  → declined
 *   any amount ending 02  → 3DS challenge, then approved on query
 *   any amount ending 03  → still pending when queried (the lost callback)
 *   anything else         → approved straight through
 *
 * It also backs the demonstration data, so the seeded purchase history is
 * produced by the same code path a real purchase takes rather than by direct
 * inserts that could drift from it.
 */
class FakeGateway implements PaymentGateway
{
    public const DECLINE_SUFFIX = 1;

    public const CHALLENGE_SUFFIX = 2;

    public const STAYS_PENDING_SUFFIX = 3;

    public function initiate(Purchase $purchase, string $callbackUrl): GatewayHandoff
    {
        $suffix = $purchase->amount_cents % 100;

        if ($suffix === self::DECLINE_SUFFIX) {
            return GatewayHandoff::failed('05', 'Do not honour', ['fake' => true]);
        }

        if ($suffix === self::CHALLENGE_SUFFIX) {
            return GatewayHandoff::challenge(
                '<form id="fake-3ds" method="post" action="'.e($callbackUrl).'">'
                    .'<input type="hidden" name="SpiToken" value="fake-spi-'.$purchase->order_identifier.'">'
                    .'</form><script>document.getElementById("fake-3ds").submit();</script>',
                'fake-spi-'.$purchase->order_identifier,
                ['fake' => true]
            );
        }

        /*
         * ⚠️ THE CALLBACK URL IS RETURNED UNTOUCHED. Do not append to it.
         *
         * It is a Laravel signed URL, and the signature is computed over the
         * WHOLE query string. Adding `?SpiToken=...` — or `&SpiToken=...` —
         * changes what is being verified and the route answers 403.
         *
         * This shipped and broke checkout on the live site. It survived the
         * suite because every other callback test posts to the signed route
         * directly, so the URL the gateway actually builds was never
         * followed. There is a test for it now.
         *
         * Appending it was pointless as well as harmful: the callback
         * identifies the purchase from the {order} route parameter and then
         * re-queries the gateway. It never reads SpiToken off the request,
         * because nothing in an unsigned callback is trusted.
         */
        return GatewayHandoff::redirect(
            $callbackUrl,
            'fake-spi-'.$purchase->order_identifier,
            ['fake' => true]
        );
    }

    public function query(Purchase $purchase): GatewayStatus
    {
        $suffix = $purchase->amount_cents % 100;

        if ($suffix === self::DECLINE_SUFFIX) {
            return GatewayStatus::declined('05', 'Do not honour', ['fake' => true]);
        }

        if ($suffix === self::STAYS_PENDING_SUFFIX) {
            return GatewayStatus::pending(['fake' => true]);
        }

        /*
         * Deterministic transaction id derived from the order. Two queries of
         * the same purchase return the SAME id, which is what makes the
         * duplicate-callback test meaningful — a random id would make every
         * grant look unique and the idempotency guarantee would never be
         * exercised.
         */
        return GatewayStatus::approved('fake-txn-'.$purchase->order_identifier, ['fake' => true]);
    }

    public function void(Purchase $purchase): bool
    {
        return true;
    }

    public function mode(): string
    {
        return 'sandbox';
    }
}
