<?php

namespace App\Support;

use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;

/**
 * Verifying that a delivery event really came from Resend.
 *
 * ⚠️ RESEND SIGNS WITH SVIX, WHICH IS A DIFFERENT SCHEME FROM META'S.
 *
 * The WhatsApp webhook takes an HMAC of the raw body alone. Svix signs
 * `{id}.{timestamp}.{body}`, base64-encodes the result rather than hex, sends
 * a SPACE-SEPARATED LIST of versioned signatures, and the secret itself is
 * base64 behind a `whsec_` prefix that must be stripped first.
 *
 * Every one of those is a way to write a verifier that always returns false —
 * and a webhook that rejects everything looks exactly like a webhook nobody is
 * sending to. So ResendWebhookTest signs a payload the way Svix does and
 * asserts the verifier accepts it, then tampers with each part in turn.
 *
 * ⚠️ THE TIMESTAMP IS CHECKED, NOT JUST THE SIGNATURE.
 *
 * A signature alone is replayable forever: an attacker who captures one valid
 * `bounced` event can resend it whenever they like, and every replay would
 * verify perfectly. Since a bounce suppresses future email to an address, a
 * replayable bounce is a way to silently cut a member off from the product.
 *
 * ⚠️ SAME INERT-NOT-REJECT SHAPE AS THE WHATSAPP WEBHOOK. An unverifiable
 * payload is acknowledged with 200 and dropped, loudly — because a provider
 * that keeps receiving failures disables the endpoint, turning a loud problem
 * into a silent one.
 */
class ResendWebhook
{
    /** Svix's headers. Lower-case; Laravel normalises them anyway. */
    public const ID_HEADER = 'svix-id';

    public const TIMESTAMP_HEADER = 'svix-timestamp';

    public const SIGNATURE_HEADER = 'svix-signature';

    /**
     * How far out of date a signed event may be.
     *
     * Svix's own recommendation. Long enough for clock skew and a slow queue,
     * short enough that a captured event stops being useful quickly.
     */
    public const TOLERANCE_SECONDS = 300;

    public function __construct(private readonly SettingsService $settings) {}

    public function signingSecret(): ?string
    {
        $value = trim((string) $this->settings->get('mail', 'webhook_secret', ''));

        return $value === '' ? null : $value;
    }

    /**
     * 'valid' | 'invalid' | 'unverifiable'
     *
     * `unverifiable` means no secret is configured, so nothing can be proved.
     * It is NOT `valid`, and the controller acts on neither.
     */
    public function signatureState(Request $request): string
    {
        $secret = $this->signingSecret();

        if ($secret === null) {
            return 'unverifiable';
        }

        $id = (string) $request->header(self::ID_HEADER, '');
        $timestamp = (string) $request->header(self::TIMESTAMP_HEADER, '');
        $presented = (string) $request->header(self::SIGNATURE_HEADER, '');

        if ($id === '' || $timestamp === '' || $presented === '') {
            return 'invalid';
        }

        if (! $this->timestampIsFresh($timestamp)) {
            return 'invalid';
        }

        $expected = $this->sign($id, $timestamp, $request->getContent(), $secret);

        if ($expected === null) {
            return 'invalid';
        }

        /*
         * ⚠️ THE HEADER CARRIES A LIST, NOT ONE SIGNATURE.
         *
         * Svix sends every currently-valid version, space separated, each
         * prefixed with its scheme — "v1,abc v1,def" during a secret rotation.
         * Comparing against the whole header string fails for a caller that is
         * behaving perfectly correctly, and the symptom is a webhook that
         * mysteriously stops working the day somebody rotates a secret.
         */
        foreach (explode(' ', $presented) as $candidate) {
            $parts = explode(',', $candidate, 2);

            if (count($parts) !== 2 || $parts[0] !== 'v1') {
                continue;
            }

            if (hash_equals($expected, $parts[1])) {
                return 'valid';
            }
        }

        return 'invalid';
    }

    /**
     * ⚠️ REPLAY PROTECTION. See the class note: a replayable `bounced` event
     * is a way to cut a member off from every email the product sends.
     */
    private function timestampIsFresh(string $timestamp): bool
    {
        if (! ctype_digit(ltrim($timestamp, '-'))) {
            return false;
        }

        return abs(time() - (int) $timestamp) <= self::TOLERANCE_SECONDS;
    }

    /**
     * The Svix signature for a payload.
     *
     * ⚠️ THE SECRET IS BASE64 BEHIND A `whsec_` PREFIX. Signing with the raw
     * string produces a verifier that rejects every genuine event — which
     * looks identical to nobody sending any.
     */
    private function sign(string $id, string $timestamp, string $body, string $secret): ?string
    {
        $material = str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret;

        $key = base64_decode($material, true);

        if ($key === false || $key === '') {
            return null;
        }

        return base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key, true));
    }
}
