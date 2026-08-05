<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessResendWebhook;
use App\Support\ResendWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Resend's delivery events.
 *
 * ⚠️ THIS ENDPOINT IS WHAT MAKES THE LEDGER ABLE TO TELL THE TRUTH.
 *
 * Until now email could reach `accepted` and no further: SMTP returns a 250 at
 * handover and reports nothing afterwards, so a hard bounce to a dead address
 * and a message read twice were indistinguishable — and both looked like
 * success. That is the vacuous-assertion pattern at the infrastructure level,
 * with an admin screen as the thing doing the lying.
 *
 * These events are the missing facts. See docs/EMAIL-DELIVERABILITY.md.
 *
 * ⚠️ SAME SHAPE AS THE WHATSAPP WEBHOOK, DELIBERATELY. Acknowledge fast, queue
 * the work, and treat an unverifiable payload as INERT rather than rejecting
 * it — a provider that keeps receiving failures disables the endpoint, which
 * converts a loud problem into a silent one.
 */
class ResendWebhookController extends Controller
{
    private const CHANNEL = 'whatsapp-webhook';

    public function __construct(private readonly ResendWebhook $webhook) {}

    public function __invoke(Request $request): JsonResponse
    {
        $signature = $this->webhook->signatureState($request);

        if ($signature === 'invalid') {
            Log::channel(self::CHANNEL)->warning('resend webhook REFUSED — signature did not verify', [
                'ip' => $request->ip(),
                'note' => 'Either a forged event, a rotated secret we do not have, or a replay '
                    .'outside the timestamp tolerance.',
            ]);

            return response()->json(['status' => 'forbidden'], 403);
        }

        $payload = json_decode((string) $request->getContent(), true);

        if ($signature === 'unverifiable') {
            /*
             * ⚠️ ACKNOWLEDGED AND DROPPED, LOUDLY.
             *
             * Everything downstream changes state — a bounce suppresses future
             * email to an address, a complaint opts a member out permanently.
             * Reachable by an anonymous POST, those are somebody else's
             * controls over a member's account.
             */
            Log::channel(self::CHANNEL)->warning('resend webhook payload DROPPED — unverifiable', [
                'reason' => 'No mail.webhook_secret configured, so this cannot be attributed to Resend.',
                'consequence' => 'Bounces and complaints are being ignored, and the ledger stays '
                    .'at "accepted" for every email.',
                'fix' => 'Settings → Email → Webhook signing secret.',
            ]);

            return response()->json(['status' => 'ok']);
        }

        if (is_array($payload)) {
            ProcessResendWebhook::dispatch($payload);
        }

        Log::channel(self::CHANNEL)->info('resend webhook', [
            'type' => $payload['type'] ?? 'unknown',
            'status' => 'queued for processing',
        ]);

        return response()->json(['status' => 'ok']);
    }
}
