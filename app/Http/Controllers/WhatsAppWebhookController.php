<?php

namespace App\Http\Controllers;

use App\Support\WhatsAppWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Meta's callback URL.
 *
 * ⚠️ THE BRIEF SAID "POST /webhook/whatsapp". THE HANDSHAKE IS A **GET**.
 *
 * Meta verifies a callback URL by issuing a GET with `hub.mode`,
 * `hub.challenge` and `hub.verify_token`, and expects the challenge echoed
 * back as plain text. Real deliveries then arrive as POST. Registering only
 * POST — which is what the specification asked for, and which its own next
 * paragraph then contradicted — produces a 405 on the verification attempt
 * and Meta reports nothing more useful than "unable to verify".
 *
 * Both verbs are registered. That is the whole of the discrepancy, and it is
 * recorded here rather than silently corrected, because the next person to
 * read the brief will otherwise think this file wandered off it.
 *
 * ── ⚠️ WHAT THIS DOES NOT DO ──────────────────────────────────────────────
 *
 * It does not process messages. Phase 3 owns that, and Phase 3 is gated on
 * the client's written acceptance of Phase 2. `receive()` acknowledges and
 * logs. It dispatches no job, writes no row, and sends nothing. Meta requires
 * a fast 200 regardless of what we intend to do with the payload, so an
 * acknowledging stub is the correct shape for it to keep having anyway.
 */
class WhatsAppWebhookController extends Controller
{
    /** The dedicated log channel, so webhook noise never buries laravel.log. */
    private const CHANNEL = 'whatsapp-webhook';

    public function __construct(private readonly WhatsAppWebhook $webhook) {}

    /**
     * GET — Meta's one-time verification handshake.
     */
    public function verify(Request $request): Response
    {
        if ($refusal = $this->refuseInsecure($request, 'verify')) {
            return $refusal;
        }

        $mode = $this->webhook->hubParameter($request, WhatsAppWebhook::MODE);
        $challenge = $this->webhook->hubParameter($request, WhatsAppWebhook::CHALLENGE);
        $presented = $this->webhook->hubParameter($request, WhatsAppWebhook::TOKEN);

        /*
         * ⚠️ NOT CONFIGURED IS ITS OWN ANSWER, NOT A REFUSAL.
         *
         * With no token stored there is nothing to compare against, and a 403
         * here would be indistinguishable from a mistyped token — sending the
         * client to check a value in Meta's dashboard when the real problem is
         * an empty field in his own settings screen. 503 plus a log line that
         * says which of the two it is costs nothing and saves an afternoon.
         */
        if ($this->webhook->verifyToken() === null) {
            $this->record($request, 'verify', 503, [
                'outcome' => 'no verify token configured',
                'fix' => 'php artisan whatsapp:verify-token, or Settings → WhatsApp → Verify token',
            ]);

            return response('Webhook not configured.', 503)
                ->header('Content-Type', 'text/plain');
        }

        if ($mode !== WhatsAppWebhook::SUBSCRIBE) {
            $this->record($request, 'verify', 400, [
                'outcome' => 'wrong hub.mode',
                'hub.mode' => $mode,
            ]);

            return response('Unexpected hub.mode.', 400)
                ->header('Content-Type', 'text/plain');
        }

        if (! $this->webhook->tokenMatches($presented)) {
            $this->record($request, 'verify', 403, [
                'outcome' => 'verify token did not match',
                // Never the value, presented or expected. Only that one arrived.
                'token_presented' => $presented !== null,
            ]);

            return response('Verification failed.', 403)
                ->header('Content-Type', 'text/plain');
        }

        if ($challenge === null || $challenge === '') {
            $this->record($request, 'verify', 400, ['outcome' => 'no hub.challenge to echo']);

            return response('Missing hub.challenge.', 400)
                ->header('Content-Type', 'text/plain');
        }

        $this->record($request, 'verify', 200, ['outcome' => 'verified, challenge echoed']);

        /*
         * ⚠️ THE BODY IS THE CHALLENGE AND NOTHING ELSE. No JSON wrapper, no
         * trailing newline — Meta compares the response body byte for byte.
         */
        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * POST — a real delivery. Acknowledged, logged, and deliberately unhandled.
     */
    public function receive(Request $request): JsonResponse|Response
    {
        if ($refusal = $this->refuseInsecure($request, 'receive')) {
            return $refusal;
        }

        $signature = $this->webhook->signatureState($request);

        if ($signature === 'invalid') {
            $this->record($request, 'receive', 403, ['outcome' => 'signature did not verify']);

            return response()->json(['status' => 'forbidden'], 403);
        }

        $payload = json_decode((string) $request->getContent(), true);

        /*
         * ⚠️ AN UNVERIFIABLE PAYLOAD IS INERT. IT IS LOGGED AND DROPPED.
         *
         * Not rejected — Meta disables a subscription that keeps failing, and
         * the callback has to stay configurable before the app secret exists.
         * But nothing downstream may act on a payload we cannot attribute to
         * Meta, because acting on one means an anonymous POST can mark a
         * delivery failed or opt a member out of a channel.
         *
         * ⚠️ THE SKIP IS LOUD, AND COUNTED. A webhook that quietly does
         * nothing is indistinguishable from a webhook that works.
         */
        if ($signature === 'unverifiable') {
            Log::channel(self::CHANNEL)->warning('whatsapp webhook payload DROPPED — unverifiable', [
                'reason' => 'No whatsapp.app_secret configured, so this payload cannot be attributed to Meta.',
                'consequence' => 'Delivery statuses and inbound STOP messages are being ignored.',
                'fix' => 'Settings → WhatsApp → Meta app secret.',
                'bytes' => strlen((string) $request->getContent()),
            ]);

            return response()->json(['status' => 'ok']);
        }

        $this->record($request, 'receive', 200, [
            'outcome' => 'acknowledged, not processed',
            'signature' => $signature,
            /*
             * ⚠️ ENCODED HERE, NOT HANDED OVER AS AN ARRAY.
             *
             * Monolog's normaliser gives up at nine levels of nesting and
             * replaces everything below with "Over 9 levels deep, aborting
             * normalization". A WhatsApp delivery is ten deep by the time it
             * reaches the message body, so passing the array logged a stub of
             * exactly the part worth reading — the log looked healthy and
             * contained nothing.
             */
            'payload' => is_array($payload)
                ? json_encode($this->webhook->redact($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : '[unparseable body, '.strlen((string) $request->getContent()).' bytes]',
        ]);

        /*
         * ⚠️ 200 EVEN WHEN THE BODY MAKES NO SENSE.
         *
         * Meta retries anything that is not a 2xx, with backoff, and disables
         * a subscription that keeps failing. Since nothing downstream depends
         * on this payload yet, a malformed body is our problem to read in the
         * log — not a reason to make Meta redeliver it.
         */
        return response()->json(['status' => 'ok']);
    }

    /**
     * Plain HTTP in production, refused. See WhatsAppWebhook::isSecure for
     * why this is a misconfiguration guard rather than a security boundary.
     */
    private function refuseInsecure(Request $request, string $verb): ?Response
    {
        if (! app()->isProduction() || $this->webhook->isSecure($request)) {
            return null;
        }

        $this->record($request, $verb, 403, ['outcome' => 'refused: not HTTPS']);

        return response('HTTPS required.', 403)->header('Content-Type', 'text/plain');
    }

    /**
     * One line per request: when, what, from where, and what we answered.
     *
     * @param  array<string, mixed>  $detail
     */
    private function record(Request $request, string $verb, int $status, array $detail): void
    {
        Log::channel(self::CHANNEL)->info('whatsapp webhook', [
            'at' => now()->toIso8601String(),
            'verb' => $verb,
            'method' => $request->method(),
            'ip' => $request->ip(),
            'status' => $status,
            // Query keys as Meta spelled them, values included: hub.challenge
            // is a random nonce and the token never reaches here unredacted.
            'query' => $verb === 'verify'
                ? array_diff_key($this->webhook->rawQuery($request), [WhatsAppWebhook::TOKEN => ''])
                : [],
            ...$detail,
        ]);
    }
}
