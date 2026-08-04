<?php

namespace App\Services\WhatsApp;

/**
 * What happened when we tried to send.
 *
 * ⚠️ `retryable` IS THE FIELD THAT MATTERS AND IT IS NOT THE SAME AS `sent`.
 *
 * Two failures look identical from the outside and must be handled in
 * opposite ways. A rate limit or a Meta outage should be retried; an invalid
 * number, an unapproved template, or a member who has blocked the business
 * should never be retried, because every attempt costs money, counts against
 * quality rating, and will fail identically forever.
 *
 * A job that retries everything three times turns one bad number into three
 * failed sends and a worse rating with Meta.
 */
class WhatsAppResult
{
    private function __construct(
        public readonly bool $sent,
        public readonly bool $retryable,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
        public readonly ?int $code = null,
    ) {}

    public static function sent(string $messageId): self
    {
        return new self(sent: true, retryable: false, messageId: $messageId);
    }

    /** Worth trying again: a timeout, a 429, a 5xx. */
    public static function temporaryFailure(string $error, ?int $code = null): self
    {
        return new self(sent: false, retryable: true, error: $error, code: $code);
    }

    /** Never worth trying again: a bad number, an unapproved template. */
    public static function permanentFailure(string $error, ?int $code = null): self
    {
        return new self(sent: false, retryable: false, error: $error, code: $code);
    }
}
