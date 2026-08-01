<?php

namespace App\Services\Payments;

use App\Models\Purchase;

/**
 * Everything the application is allowed to know about taking money.
 *
 * ⚠️ Sandbox versus live is CONFIGURATION, never a second implementation.
 * There is exactly one PowerTranzGateway and it reads its credentials, mode
 * and host from the settings table. Going live is a credential swap in the
 * client's browser, not a deploy — which matters, because his merchant
 * account is weeks or months away and he is travelling.
 *
 * FakeGateway exists for tests only. No test may reach the network.
 */
interface PaymentGateway
{
    /** Begin a payment. Returns whatever the browser has to do next. */
    public function initiate(Purchase $purchase, string $callbackUrl): GatewayHandoff;

    /**
     * Ask the gateway what actually happened.
     *
     * ⚠️ Always called before granting a single credit, including when a
     * callback has just told us it succeeded. The callback is a signal, not
     * evidence.
     */
    public function query(Purchase $purchase): GatewayStatus;

    /** Reverse an authorised-but-uncaptured payment, where supported. */
    public function void(Purchase $purchase): bool;

    /** 'sandbox' or 'live' — recorded on every purchase row. */
    public function mode(): string;
}
