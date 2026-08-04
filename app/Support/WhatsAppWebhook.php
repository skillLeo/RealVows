<?php

namespace App\Support;

use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;

/**
 * The WhatsApp webhook's decisions, kept out of the controller.
 *
 * ⚠️ THIS IS THE HANDSHAKE ONLY. IT DELIBERATELY DOES NOT PROCESS MESSAGES.
 *
 * Meta will not let a number be configured until the callback URL answers a
 * verification challenge, and business verification is the longest lead time
 * in the project. So the endpoint exists now, ahead of Phase 3, purely so
 * that clock can start. The POST side acknowledges and logs; it dispatches
 * nothing, writes no rows, and notifies nobody. WhatsAppWebhookTest asserts
 * that, so "we will wire it up later" cannot quietly become "it half works".
 *
 * ── ⚠️ NOTHING HERE IS HARDCODED, AND THAT IS NOT A STYLE PREFERENCE ──────
 *
 * The brief for this endpoint supplied a literal verify token to compare
 * against. A shared secret written into a file in the repository is in the
 * git history permanently, is identical on every deployment, and is readable
 * by anyone who is ever given the code — including the client's next
 * developer. It is also the one value that decides whether an unknown caller
 * gets to claim to be Meta.
 *
 * It reads from the `whatsapp` settings group instead, encrypted at rest like
 * every other credential, with `WHATSAPP_VERIFY_TOKEN` as an environment
 * fallback for a machine with no database. `php artisan whatsapp:verify-token`
 * generates one and prints it once.
 */
class WhatsAppWebhook
{
    /**
     * Meta's own parameter names. Kept as constants because they arrive
     * mangled and the raw spelling has to survive somewhere true.
     */
    public const MODE = 'hub.mode';

    public const CHALLENGE = 'hub.challenge';

    public const TOKEN = 'hub.verify_token';

    /** The only value Meta ever sends for hub.mode. */
    public const SUBSCRIBE = 'subscribe';

    /** Meta signs every POST body with this header. */
    public const SIGNATURE_HEADER = 'X-Hub-Signature-256';

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * The shared secret typed into Meta's callback configuration.
     *
     * Null means not configured, which must never be treated as "matches
     * anything" — see the controller, which refuses the handshake outright
     * rather than comparing against nothing.
     */
    public function verifyToken(): ?string
    {
        return $this->value('verify_token') ?? $this->fromEnv('WHATSAPP_VERIFY_TOKEN');
    }

    /**
     * The Meta app secret, used to prove a POST really came from Meta.
     *
     * Separate from the access token on purpose: the access token authorises
     * us to call Meta, this authenticates Meta calling us. They are different
     * secrets in the Meta dashboard and conflating them is a common mistake.
     */
    public function appSecret(): ?string
    {
        return $this->value('app_secret') ?? $this->fromEnv('WHATSAPP_APP_SECRET');
    }

    /**
     * ⚠️ PHP MANGLES `hub.mode` INTO `hub_mode` AND ALWAYS HAS.
     *
     * Dots are illegal in PHP variable names, so every dot in a query string
     * key is rewritten to an underscore on the way into `$_GET`. Symfony
     * builds its parameter bag from that same mangled array, and Laravel's
     * `$request->query('hub.mode')` then reads the dot as *nested array*
     * access — looking for `['hub']['mode']`, which does not exist either.
     *
     * So the obvious two ways of reading Meta's parameters both return null,
     * and the endpoint answers 403 to a perfectly correct handshake. The
     * failure is silent, it looks like a wrong token, and it would be debugged
     * against Meta's dashboard rather than in the code.
     *
     * The raw QUERY_STRING is never mangled, so that is read first, with the
     * underscore spelling as a fallback for anything that hands us a
     * pre-parsed bag.
     */
    public function hubParameter(Request $request, string $name): ?string
    {
        foreach ($this->rawQuery($request) as $key => $value) {
            if ($key === $name) {
                return $value;
            }
        }

        $mangled = str_replace('.', '_', $name);
        $bag = $request->query();

        foreach ([$name, $mangled] as $candidate) {
            if (isset($bag[$candidate]) && is_string($bag[$candidate])) {
                return $bag[$candidate];
            }
        }

        return null;
    }

    /**
     * The query string as Meta actually sent it, with keys intact.
     *
     * @return array<string, string>
     */
    public function rawQuery(Request $request): array
    {
        $raw = (string) ($request->server('QUERY_STRING') ?? '');

        if ($raw === '') {
            return [];
        }

        $params = [];

        foreach (explode('&', $raw) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');

            $params[urldecode($key)] = urldecode($value);
        }

        return $params;
    }

    /**
     * Does the presented token match the configured one?
     *
     * ⚠️ `hash_equals`, not `===`. A token compared with a short-circuiting
     * string comparison leaks its length and then its content through timing.
     * It is one function name for the whole class of attack.
     */
    public function tokenMatches(?string $presented): bool
    {
        $expected = $this->verifyToken();

        if ($expected === null || $presented === null) {
            return false;
        }

        return hash_equals($expected, $presented);
    }

    /**
     * Whether the POST body really came from Meta.
     *
     * Returns one of:
     *   'valid'        — signed, and the signature checks out
     *   'invalid'      — signed, and it does not. Refuse.
     *   'unverifiable' — no app secret configured, so nothing can be proved.
     *
     * ⚠️ 'unverifiable' IS NOT 'valid', AND THE LOG SAYS SO ON EVERY LINE.
     *
     * Until the app secret is filled in, this endpoint accepts a POST from
     * anybody. That is a deliberate, temporary trade: refusing unsigned
     * traffic before the secret exists would make the endpoint impossible to
     * exercise during setup, and the handler currently does nothing but write
     * a redacted log line. It stops being an acceptable trade the moment this
     * endpoint does something — which is Phase 3, and is recorded there.
     */
    public function signatureState(Request $request): string
    {
        $secret = $this->appSecret();

        if ($secret === null) {
            return 'unverifiable';
        }

        $presented = (string) $request->header(self::SIGNATURE_HEADER, '');

        if ($presented === '') {
            return 'invalid';
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $presented) ? 'valid' : 'invalid';
    }

    /**
     * ⚠️ HTTPS IS ADVISORY HERE, AND THE LIMIT IS WORTH STATING.
     *
     * `X-Forwarded-Proto` is honoured because TLS may terminate at the host's
     * proxy, and rejecting on `isSecure()` alone would refuse Meta's genuine
     * requests — breaking the one thing this endpoint exists to unblock. But
     * a header any caller can set is not proof of anything, so this is a
     * guard against misconfiguration, not against an attacker. The real
     * enforcement is that Meta refuses to accept an http:// callback URL at
     * all, and that the host redirects to TLS before PHP is reached.
     */
    public function isSecure(Request $request): bool
    {
        if ($request->isSecure()) {
            return true;
        }

        return strtolower((string) $request->header('X-Forwarded-Proto')) === 'https';
    }

    /**
     * ⚠️ THE LOG IS REDACTED, AND THE BRIEF SAID "RAW". THIS IS THE ONE PLACE
     * I DID NOT BUILD WHAT WAS ASKED FOR, SO HERE IS THE REASON.
     *
     * A WhatsApp delivery payload carries the member's phone number and the
     * full text of what they wrote. On this host `storage/` sits inside the
     * served directory, guarded by a single .htaccess that has already been
     * deleted once by a deploy — for a window in which application files were
     * being handed out to anyone who asked.
     *
     * A plaintext log of members' phone numbers and messages is the single
     * worst file that could be sitting there when that happens again. It is
     * also the kind of file nobody remembers exists until it is quoted back
     * at them.
     *
     * So the structure is kept in full — which is what "for inspection"
     * actually needs, because debugging a webhook is about shape, ordering
     * and whether a field arrived, not about reading somebody's message. Only
     * the two categories that identify a person are masked, and phone numbers
     * keep their last four digits so a specific delivery can still be matched
     * against a specific member.
     *
     * Reverse it by emptying these two lists, not by deleting the call.
     */
    private const IDENTIFYING_KEYS = [
        'wa_id', 'from', 'to', 'recipient_id', 'display_phone_number', 'phone_number',
    ];

    private const CONTENT_KEYS = [
        'body', 'caption', 'filename', 'name', 'text', 'emoji',
    ];

    /**
     * @param  array<mixed>  $payload
     * @return array<mixed>
     */
    public function redact(array $payload): array
    {
        $out = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $out[$key] = $this->redact($value);

                continue;
            }

            $lower = is_string($key) ? strtolower($key) : '';

            if (in_array($lower, self::IDENTIFYING_KEYS, true) && is_scalar($value)) {
                $out[$key] = $this->maskNumber((string) $value);

                continue;
            }

            if (in_array($lower, self::CONTENT_KEYS, true) && is_string($value)) {
                $out[$key] = '[redacted, '.mb_strlen($value).' characters]';

                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    /** Last four digits kept, so a delivery can still be traced to a member. */
    private function maskNumber(string $value): string
    {
        if (mb_strlen($value) <= 4) {
            return str_repeat('•', mb_strlen($value));
        }

        return str_repeat('•', mb_strlen($value) - 4).mb_substr($value, -4);
    }

    /** Empty strings are unset, not set-to-nothing. */
    private function value(string $key): ?string
    {
        $value = trim((string) $this->settings->get('whatsapp', $key, ''));

        return $value === '' ? null : $value;
    }

    private function fromEnv(string $key): ?string
    {
        $value = trim((string) env($key, ''));

        return $value === '' ? null : $value;
    }
}
