<?php

namespace App\Services\Payments;

/**
 * Strips card data out of anything on its way to storage or a log.
 *
 * ⚠️ Written before the gateway, and every payload routes through it — not
 * as a display filter. A display filter leaves the real number in the
 * database forever, where the next screen someone writes will find it.
 *
 * Deliberately paranoid: it redacts by key name, and then sweeps the
 * remaining string values for anything that merely looks like a card number,
 * because a gateway is free to add a field tomorrow that nobody here
 * anticipated.
 */
class PaymentRedactor
{
    /** Matched case-insensitively as substrings, so `CardPan` catches too. */
    private const SENSITIVE_KEYS = [
        'pan', 'cardnumber', 'card_number', 'accountnumber', 'account_number',
        'cvv', 'cvc', 'cvn', 'cid', 'securitycode', 'security_code',
        'expiry', 'expiration', 'expdate', 'exp_date', 'expirydate',
        'track1', 'track2', 'magstripe',
        'password', 'powertranzpassword', 'apikey', 'api_key', 'secret',
        'authorization', 'auth_token',
    ];

    public function redact(mixed $payload): mixed
    {
        if (is_array($payload)) {
            $out = [];

            foreach ($payload as $key => $value) {
                $out[$key] = $this->isSensitiveKey((string) $key)
                    ? $this->mask($value)
                    : $this->redact($value);
            }

            return $out;
        }

        return is_string($payload) ? $this->scrubString($payload) : $payload;
    }

    private function isSensitiveKey(string $key): bool
    {
        $needle = strtolower(preg_replace('/[^a-z0-9]/i', '', $key));

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($needle, str_replace('_', '', $sensitive))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keeps the last four of a card-length value so a transaction is still
     * identifiable down a phone line, and hides everything else entirely.
     */
    private function mask(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '[redacted]';
        }

        $digits = preg_replace('/\D/', '', (string) $value);

        return strlen($digits) >= 12
            ? '••••••••'.substr($digits, -4)
            : '[redacted]';
    }

    /**
     * The safety net for fields nobody named. Anything with 13–19 digits in a
     * card-like shape is masked wherever it appears in a string.
     */
    private function scrubString(string $value): string
    {
        return (string) preg_replace_callback(
            '/\b(?:\d[ -]?){12,18}\d\b/',
            function (array $m) {
                $digits = preg_replace('/\D/', '', $m[0]);

                return '••••••••'.substr($digits, -4);
            },
            $value
        );
    }
}
