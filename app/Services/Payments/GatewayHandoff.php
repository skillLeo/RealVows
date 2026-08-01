<?php

namespace App\Services\Payments;

/**
 * What the member's browser has to do next to pay.
 *
 * Either a URL to send them to (hosted payment page) or a block of HTML that
 * auto-submits itself to the issuing bank (3DS challenge). Both shapes exist
 * because PowerTranz uses both depending on the acquirer, and the checkout
 * screen should not have to know which.
 */
class GatewayHandoff
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $html = null,
        public readonly ?string $spiToken = null,
        public readonly ?string $transactionId = null,
        public readonly ?string $failureCode = null,
        public readonly ?string $failureMessage = null,
        public readonly array $raw = [],
    ) {}

    public static function redirect(string $url, ?string $spiToken = null, array $raw = []): self
    {
        return new self(ok: true, redirectUrl: $url, spiToken: $spiToken, raw: $raw);
    }

    public static function challenge(string $html, string $spiToken, array $raw = []): self
    {
        return new self(ok: true, html: $html, spiToken: $spiToken, raw: $raw);
    }

    public static function failed(string $code, string $message, array $raw = []): self
    {
        return new self(ok: false, failureCode: $code, failureMessage: $message, raw: $raw);
    }
}
