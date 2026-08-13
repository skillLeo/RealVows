<?php

namespace App\Support;

/**
 * The seven message templates this codebase sends.
 *
 * ⚠️ THE BODIES HERE ARE NOT THE BODIES MEMBERS RECEIVE.
 *
 * This docblock used to say "exactly as submitted to Meta". That was false, and
 * a screenshot of the Meta dashboard is what proved it: the approved body of
 * `verification_rejected` reads "Hi {{1}}, your RealVows verification needs
 * attention. Reason: {{2}}. Please log in to update your profile." — different
 * wording from the text below, no link, and no opt-out line.
 *
 * Meta stores the approved body. We send only a NAME, a LANGUAGE and an ORDERED
 * LIST OF VALUES. The sentence on a member's lock screen is Meta's, not ours,
 * and editing the text below changes nothing about what anybody reads.
 *
 * ⚠️ SO EVERY GUARD THAT SCANS THESE BODIES IS SCANNING AN INTENTION.
 *
 * The marriage-lead reader, the forbidden-terms reader and the opt-out rule all
 * read this file. They are still worth having — they keep what we ask Meta to
 * approve honest — but they cannot tell you what was delivered. The only check
 * pointed at the real emission point is:
 *
 *     php artisan realvows:whatsapp-templates-check
 *
 * which reads the approved templates back from the Graph API and compares them.
 * Run it after anybody edits a template in the dashboard.
 *
 * ⚠️ WHAT IS STILL A HARD CONTRACT: the NAME, the LANGUAGE, and the NUMBER of
 * variables. Those three must match Meta exactly or the send fails.
 *
 * A WhatsApp template is approved as a fixed body with numbered placeholders.
 * We then send an ORDERED, UNNAMED list of values and Meta drops them into
 * {{1}}, {{2}} … positionally. There is no key, no validation, and no error.
 *
 * ⚠️ A WRONG ORDER SENDS SUCCESSFULLY AND SAYS THE WRONG THING.
 *
 * Swap two variables in `mutual_interest` and every member is told their own
 * name is the person who liked them back. The API returns 200, the delivery
 * status reads "delivered", the admin panel is green, and the only thing that
 * is wrong is the sentence a real person reads on their lock screen. Nothing
 * anywhere else in this codebase fails this quietly.
 *
 * So the order lives here, next to the approved body, and
 * WhatsAppTemplateTest asserts that every call site passes variables in this
 * order — with a positive control proving the check can still detect a swap.
 *
 * ── ⚠️ BODY RULES, ALL OF WHICH ARE ENFORCED BY TEST ──────────────────────
 *
 *  1. NO AFFINITY LANGUAGE. The engine weights affinity internally and the
 *     vocabulary is permanently banned from every surface a member can reach.
 *     A template body is submitted to Meta, reviewed by a stranger, and then
 *     sits on a lock screen — it is the most public surface in the product.
 *  2. NO COMPATIBILITY SCORE. A number on a lock screen is legible to anyone
 *     holding the phone. The score lives behind the link.
 *  3. NO IDENTIFYING DETAIL about a third party unless both people have
 *     agreed — which is true of exactly one template, `mutual_interest`.
 *  4. EVERY BODY ENDS WITH THE OPT-OUT LINE.
 *  5. NO VARIABLE AT THE VERY START OR VERY END of a body. Meta rejects that
 *     outright and it is the commonest cause of a first-attempt rejection.
 */
class WhatsAppTemplates
{
    /** Meta's categories. Utility approves faster and costs less than marketing. */
    public const UTILITY = 'UTILITY';

    public const AUTHENTICATION = 'AUTHENTICATION';

    /**
     * ⚠️ MARKETING IS NOT A CHOICE WE MADE. META IMPOSED IT.
     *
     * `someone_interested` and `introduction_unlocked` were submitted as
     * Utility and Meta recategorised both, on the grounds that their content
     * does not meet its utility guidelines. There is a 60-day appeal window
     * (to 5 October 2026) and the client has chosen not to appeal.
     *
     * ⚠️ THE CONSEQUENCE IS REAL AND IT IS NOT ONLY COST. A marketing template
     * costs more per message, and it is subject to per-user marketing limits
     * and marketing opt-out in a way a utility template is not. A member who
     * has opted out of marketing will not receive these two, while still
     * receiving the other four.
     *
     * So a member can be told their profile was verified and NOT told somebody
     * is interested in them. That is a worse gap than it sounds, and it is why
     * the categories are recorded here honestly rather than left as the
     * aspiration we submitted.
     */
    public const MARKETING = 'MARKETING';

    /** The line every body ends with, so opting out is never buried. */
    public const OPT_OUT = 'Reply STOP to stop receiving these messages.';

    /**
     * ⚠️ `variables` IS THE ORDER, AND IT IS THE WHOLE POINT OF THIS FILE.
     *
     * The names are documentation; the POSITION is the contract. Reordering
     * this array without re-submitting the template to Meta silently corrupts
     * every message that template sends.
     *
     * @var array<string, array{category: string, variables: list<string>, body: string}>
     */
    public const TEMPLATES = [
        /*
         * 1 — A new introduction is waiting.
         */
        'introduction_delivered' => [
            'category' => self::UTILITY,
            'variables' => ['first_name', 'count'],
            'body' => "Hello {{1}}, someone new is waiting for you on RealVows — {{2}} introduction to open when you are ready.\n\nOpen it here: realvows.com/dashboard\n\n".self::OPT_OUT,
        ],

        /*
         * 2 — Payment succeeded and introductions are now open.
         */
        'introduction_unlocked' => [
            'category' => self::MARKETING,
            'variables' => ['first_name', 'count'],
            'body' => "Hello {{1}}, your payment is complete and {{2}} introduction is now open.\n\nRead it here: realvows.com/dashboard\n\n".self::OPT_OUT,
        ],

        /*
         * 3 — Both people said yes. The ONLY template naming another member,
         * and it may only ever be sent once both have agreed.
         */
        'mutual_interest' => [
            'category' => self::UTILITY,
            'variables' => ['first_name', 'other_first_name'],
            'body' => "Hello {{1}}, good news — {{2}} is interested in you as well. You can now see how to get in touch.\n\nOpen it here: realvows.com/dashboard\n\n".self::OPT_OUT,
        ],

        /*
         * 4 — Somebody is interested in you.
         *
         * ⚠️ ONE VARIABLE, AND IT IS THE RECIPIENT'S OWN NAME. There is no
         * slot for the other person because there is nothing to say about
         * them: they have said yes and the recipient has not answered, so
         * naming them would reveal a one-sided interest the other member has
         * not consented to disclose.
         */
        'someone_interested' => [
            'category' => self::MARKETING,
            'variables' => ['first_name'],
            'body' => "Hello {{1}}, someone you were introduced to has expressed interest in you.\n\nOpen your introductions to see who: realvows.com/dashboard\n\n".self::OPT_OUT,
        ],

        /*
         * 5 — Verification approved.
         */
        'verification_approved' => [
            'category' => self::UTILITY,
            'variables' => ['first_name'],
            'body' => "Hello {{1}}, your profile has been verified. Your introductions are on their way.\n\nrealvows.com/dashboard\n\n".self::OPT_OUT,
        ],

        /*
         * 6 — Verification needs attention. Carries the reason, because a
         * rejection without one is a dead end.
         */
        'verification_rejected' => [
            'category' => self::UTILITY,
            'variables' => ['first_name', 'reason'],
            'body' => "Hello {{1}}, your profile needs a little more before it can be verified. Reason: {{2}}\n\nYou can put it right here: realvows.com/account\n\n".self::OPT_OUT,
        ],

        /*
         * 7 — The six-digit code.
         *
         * ⚠️ AUTHENTICATION, NOT UTILITY. Meta reviews this category
         * separately, and an authentication template submitted as utility is
         * rejected. It is also the one body with no opt-out line: a code the
         * member just asked for is not a notification they can unsubscribe
         * from, and Meta's authentication format does not permit the extra
         * text.
         */
        'phone_verification_code' => [
            'category' => self::AUTHENTICATION,
            'variables' => ['code'],
            'body' => 'Your RealVows verification code is {{1}}. It expires in ten minutes.',
        ],
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::TEMPLATES);
    }

    /**
     * The declared variable order for a template.
     *
     * @return list<string>
     */
    public static function variablesFor(string $template): array
    {
        return self::TEMPLATES[$template]['variables']
            ?? throw new \InvalidArgumentException("Unknown WhatsApp template [{$template}].");
    }

    public static function bodyFor(string $template): string
    {
        return self::TEMPLATES[$template]['body']
            ?? throw new \InvalidArgumentException("Unknown WhatsApp template [{$template}].");
    }

    /**
     * ⚠️ THE ONE FUNCTION EVERY CALL SITE MUST USE.
     *
     * Callers pass a KEYED array — `['first_name' => 'Aisha', 'count' => '1']`
     * — and this converts it to the positional list Meta expects, using the
     * declared order. A caller therefore cannot get the order wrong, because
     * it never states an order.
     *
     * It throws on a missing or unexpected key rather than sending a message
     * with a blank slot in it. Meta rejects a template whose parameter count
     * does not match, so a silent omission would fail at the API anyway — but
     * failing here names the missing variable instead of returning error 132000.
     *
     * @param  array<string, string>  $values
     * @return list<string>
     */
    public static function order(string $template, array $values): array
    {
        $declared = self::variablesFor($template);

        $missing = array_diff($declared, array_keys($values));
        $unexpected = array_diff(array_keys($values), $declared);

        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException(sprintf(
                'Template [%s] expects exactly [%s].%s%s',
                $template,
                implode(', ', $declared),
                $missing !== [] ? ' Missing: '.implode(', ', $missing).'.' : '',
                $unexpected !== [] ? ' Unexpected: '.implode(', ', $unexpected).'.' : '',
            ));
        }

        return array_map(
            fn (string $name) => $values[$name],
            $declared,
        );
    }
}
