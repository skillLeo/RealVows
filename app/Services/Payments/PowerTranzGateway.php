<?php

namespace App\Services\Payments;

use App\Models\Purchase;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PowerTranz, the Caribbean acquirer the client's bank uses.
 *
 * ⚠️ There is NO Laravel Cashier support for PowerTranz and no maintained
 * package that wraps it. This is a hand-rolled integration and it is more
 * work than Stripe, not less. Do not go looking for a shortcut.
 *
 * ⚠️ Sandbox versus live is a SETTING, not a code path. There is one class,
 * and it reads its credentials, mode and host from the encrypted settings
 * table. Going live is the client typing his real merchant id into
 * /admin/settings — not a deploy, which matters because his merchant account
 * needs a registered business name, then a bank account, then underwriting,
 * and he is travelling.
 *
 * ── The flow, which is two passes and not one ─────────────────────────────
 *
 *  1. Pre-authentication. POST to /Auth with the 3DS flag. The response
 *     carries an SpiToken and RedirectData — an HTML form.
 *  2. Challenge. That form auto-submits in the member's browser. The issuing
 *     bank may be frictionless, may fingerprint the device, or may put up a
 *     full challenge.
 *  3. Return. The gateway POSTs back to our callback with the SpiToken.
 *  4. Completion. POST to /Payment with that token for the final approval.
 *
 * Only after step 4 does anything get credited.
 */
class PowerTranzGateway implements PaymentGateway
{
    /**
     * ⚠️ ISO 4217 NUMERIC, AS A STRING. USD is '840'.
     *
     * Sending 'USD' fails validation with an unhelpful error. This constant
     * exists so nobody has to rediscover that.
     */
    private const CURRENCY_USD = '840';

    public const HOSTS = [
        'sandbox' => 'https://staging.ptranz.com/api',
        'live' => 'https://gateway.ptranz.com/api',
    ];

    private const TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly PaymentRedactor $redactor,
    ) {}

    public function mode(): string
    {
        return $this->settings->get('payments', 'environment') === 'live' ? 'live' : 'sandbox';
    }

    public function initiate(Purchase $purchase, string $callbackUrl): GatewayHandoff
    {
        $payload = [
            'TransactionIdentifier' => $purchase->order_identifier,
            'TotalAmount' => round($purchase->amount_cents / 100, 2),
            'CurrencyCode' => self::CURRENCY_USD,
            'ThreeDSecure' => true,
            'OrderIdentifier' => $purchase->order_identifier,
            'BillingAddress' => [
                'FirstName' => $purchase->user?->profile?->first_name ?? 'RealVows',
                'EmailAddress' => $purchase->user?->email,
            ],
            'ExtendedData' => [
                'ThreeDSecure' => [
                    'ChallengeWindowSize' => 5,
                    'ChallengeIndicator' => '01',
                ],
                'MerchantResponseUrl' => $callbackUrl,
            ],
        ];

        $response = $this->post('/spi/Auth', $payload, $purchase);

        if ($response === null) {
            return GatewayHandoff::failed('network', 'We could not reach the payment gateway.');
        }

        $body = $response->json() ?? [];

        // 3DS challenge required: the gateway hands back a form to submit.
        if (! empty($body['RedirectData']) && ! empty($body['SpiToken'])) {
            return GatewayHandoff::challenge($body['RedirectData'], $body['SpiToken'], $body);
        }

        // Frictionless: approved without a challenge.
        if (($body['Approved'] ?? false) === true) {
            return GatewayHandoff::redirect(
                $callbackUrl,
                $body['SpiToken'] ?? null,
                $body
            );
        }

        return GatewayHandoff::failed(
            (string) ($body['IsoResponseCode'] ?? 'unknown'),
            (string) ($body['ResponseMessage'] ?? 'The payment was not approved.'),
            $body
        );
    }

    /**
     * ⚠️ THE AUTHORITATIVE ANSWER. Always called before a credit is granted,
     * including when a callback has just claimed success — the callback is
     * not evidence, because PowerTranz does not sign it.
     */
    public function query(Purchase $purchase): GatewayStatus
    {
        if (! $purchase->spi_token) {
            return GatewayStatus::declined('no_token', 'No gateway token was recorded for this purchase.');
        }

        $response = $this->post('/spi/Payment', $purchase->spi_token, $purchase, raw: true);

        if ($response === null) {
            /*
             * A network failure is NOT a decline. Saying it was would strand
             * a member who has genuinely paid. Left pending so
             * ReconcilePendingPayments asks again later.
             */
            return GatewayStatus::pending(['error' => 'gateway unreachable']);
        }

        $body = $response->json() ?? [];

        if (($body['Approved'] ?? false) === true) {
            $transactionId = $body['TransactionIdentifier']
                ?? $body['OriginalTrxnIdentifier']
                ?? $purchase->order_identifier;

            return GatewayStatus::approved((string) $transactionId, $body);
        }

        // Still working. Not a failure yet.
        if (in_array($body['ResponseCode'] ?? null, ['SP4', 'HP0'], true)) {
            return GatewayStatus::pending($body);
        }

        return GatewayStatus::declined(
            (string) ($body['IsoResponseCode'] ?? 'declined'),
            (string) ($body['ResponseMessage'] ?? 'The payment was declined.'),
            $body
        );
    }

    public function void(Purchase $purchase): bool
    {
        if (! $purchase->gateway_transaction_id) {
            return false;
        }

        $response = $this->post('/spi/Void', [
            'TransactionIdentifier' => $purchase->gateway_transaction_id,
            'ExternalIdentifier' => $purchase->order_identifier,
        ], $purchase);

        return (bool) ($response?->json('Approved') ?? false);
    }

    /**
     * One place for headers, timeouts, idempotency and logging.
     *
     * ⚠️ The Idempotency-Key is what stops a network timeout followed by a
     * retry from charging the member twice. It is derived from our own order
     * identifier and the endpoint, so a retry of the same call carries the
     * same key while a genuinely different call does not.
     */
    private function post(string $path, mixed $payload, Purchase $purchase, bool $raw = false): ?\Illuminate\Http\Client\Response
    {
        $host = self::HOSTS[$this->mode()];
        $url = $host.$path;

        try {
            $request = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'PowerTranz-PowerTranzId' => (string) $this->settings->get('payments', 'merchant_id'),
                    'PowerTranz-PowerTranzPassword' => (string) $this->settings->get('payments', 'api_key'),
                    'Content-Type' => 'application/json',
                    'Idempotency-Key' => $this->idempotencyKey($purchase, $path),
                ]);

            // The /Payment endpoint takes a bare quoted token, not an object.
            $response = $raw
                ? $request->withBody(json_encode($payload), 'application/json')->post($url)
                : $request->post($url, $payload);

            $this->log($purchase, 'outbound', $path, is_array($payload) ? $payload : ['token' => '[redacted]'], $response->status());
            $this->log($purchase, 'inbound', $path, $response->json() ?? [], $response->status());

            return $response;
        } catch (ConnectionException $e) {
            /*
             * A timeout is genuinely ambiguous: the payment may have gone
             * through. Recorded as its own event so reconciliation and any
             * later dispute have something to read.
             */
            $this->log($purchase, 'outbound', $path, ['error' => $e->getMessage()], null, 'Connection failed or timed out.');

            Log::error('PowerTranz unreachable', [
                'purchase_id' => $purchase->id,
                'path' => $path,
            ]);

            return null;
        }
    }

    /**
     * Stable for a given purchase and endpoint, different for anything else.
     *
     * That is the whole requirement: a retry of the same call must carry the
     * same key so the gateway recognises it, while a genuinely different call
     * must not. Derived rather than stored, so a retry after a crash still
     * produces the same key.
     */
    private function idempotencyKey(Purchase $purchase, string $path): string
    {
        return 'rv-'.hash('sha256', $purchase->order_identifier.'|'.$path);
    }

    /** Everything, redacted, in both directions. */
    private function log(Purchase $purchase, string $direction, string $endpoint, array $payload, ?int $status, ?string $note = null): void
    {
        \App\Models\PaymentEvent::create([
            'purchase_id' => $purchase->id,
            'direction' => $direction,
            'endpoint' => $endpoint,
            'http_status' => $status,
            'payload' => $this->redactor->redact($payload),
            'note' => $note,
        ]);
    }
}
