<?php

namespace App\Services\Payments;

use App\Models\Purchase;

/**
 * The gateway's own answer about a transaction.
 *
 * ⚠️ This is the ONLY thing permitted to move a purchase to completed. The
 * inbound callback is not proof of anything — there is no signed webhook
 * here, unlike Stripe — so the callback is treated purely as a signal
 * meaning "go and ask now", and this is the answer that counts.
 */
class GatewayStatus
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $transactionId = null,
        public readonly ?string $failureCode = null,
        public readonly ?string $failureMessage = null,
        public readonly array $raw = [],
    ) {}

    public static function approved(string $transactionId, array $raw = []): self
    {
        return new self(Purchase::STATUS_COMPLETED, transactionId: $transactionId, raw: $raw);
    }

    public static function declined(string $code, string $message, array $raw = []): self
    {
        return new self(Purchase::STATUS_FAILED, failureCode: $code, failureMessage: $message, raw: $raw);
    }

    public static function pending(array $raw = []): self
    {
        return new self(Purchase::STATUS_PENDING_3DS, raw: $raw);
    }

    public function isApproved(): bool
    {
        return $this->status === Purchase::STATUS_COMPLETED;
    }
}
