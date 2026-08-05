<?php

namespace Tests\Feature;

use App\Support\WhatsAppTemplates;
use Tests\Support\Scanners;
use Tests\TestCase;

/**
 * ⚠️ THE FAILURE THIS GUARDS AGAINST SENDS SUCCESSFULLY.
 *
 * WhatsApp template variables are POSITIONAL. We hand Meta an ordered list
 * and it drops the values into {{1}}, {{2}} … There is no key, no schema, and
 * no error for getting it the wrong way round.
 *
 * Swap the two variables in `mutual_interest` and every member is told their
 * own name is the person who liked them back. The API returns 200. The
 * delivery status reads "delivered". The admin panel is green. The only thing
 * that is wrong is the sentence a real person reads on their lock screen.
 *
 * Nothing else in this codebase fails this quietly, which is why the order
 * is declared in one place and asserted here.
 */
class WhatsAppTemplateTest extends TestCase
{
    /**
     * ⚠️ DOES THE REPOSITIONING FORCE A RESUBMISSION TO META?
     *
     * All seven templates are in review with Meta. Editing one means
     * resubmitting and losing days, so "do any of them lead with marriage?" is
     * a question with a real cost attached to the answer.
     *
     * ⚠️ IT IS ANSWERED HERE RATHER THAN IN A REPORT, and that is the point.
     * A figure that appears in a second document is re-derived, never carried
     * across — a human reading seven strings and writing "all clean" is exactly
     * the kind of claim this project has been wrong about before. This asserts
     * it against the templates themselves, and keeps asserting it when somebody
     * edits one in six months and does not remember the constraint.
     */
    public function test_no_template_carries_marriage_forward_language(): void
    {
        $offences = [];

        foreach (WhatsAppTemplates::TEMPLATES as $name => $template) {
            $found = Scanners::marriageForwardTerms($template['body']);

            if ($found !== []) {
                $offences[] = "{$name} contains [".implode(', ', $found).']';
            }
        }

        $this->assertSame(
            [],
            $offences,
            "A WhatsApp template carries marriage-forward language. Changing it means "
                ."RESUBMITTING TO META and waiting for review again — report before "
                ."editing:\n  ".implode("\n  ", $offences)
        );
    }

    /** All seven exist, and nobody has quietly dropped one. */
    public function test_all_seven_templates_are_defined(): void
    {
        $expected = [
            'introduction_delivered',
            'introduction_unlocked',
            'mutual_interest',
            'someone_interested',
            'verification_approved',
            'verification_rejected',
            'phone_verification_code',
        ];

        $this->assertSame(
            $expected,
            WhatsAppTemplates::names(),
            'The template registry no longer matches the seven templates submitted to Meta. '
                .'Adding or renaming one here without re-submitting it means every send using it '
                .'fails permanently with error 132001.'
        );
    }

    /**
     * ⚠️ THE ORDERING HELPER IS WHAT MAKES A SWAP IMPOSSIBLE.
     *
     * Callers pass a keyed array and never state an order, so there is no
     * order for them to get wrong.
     */
    public function test_values_are_ordered_by_the_declared_position(): void
    {
        $ordered = WhatsAppTemplates::order('mutual_interest', [
            // Deliberately supplied in the WRONG order, which is the point:
            // a keyed array has no order to be wrong about.
            'other_first_name' => 'Omar',
            'first_name' => 'Aisha',
        ]);

        $this->assertSame(['Aisha', 'Omar'], $ordered);
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). The test above passes just as well against
     * a helper that returns `array_values($values)` and got lucky, so this
     * proves the declared order is genuinely what drives the result.
     */
    public function test_the_ordering_helper_actually_reorders(): void
    {
        $declared = WhatsAppTemplates::variablesFor('mutual_interest');

        $this->assertSame(['first_name', 'other_first_name'], $declared);

        // Same values, opposite insertion order — the output must not change.
        $a = WhatsAppTemplates::order('mutual_interest', ['first_name' => 'A', 'other_first_name' => 'B']);
        $b = WhatsAppTemplates::order('mutual_interest', ['other_first_name' => 'B', 'first_name' => 'A']);

        $this->assertSame($a, $b, 'The helper is returning insertion order, so it reorders nothing.');
        $this->assertSame(['A', 'B'], $a);

        // And it can tell a real swap apart from a correct call.
        $this->assertNotSame(
            WhatsAppTemplates::order('mutual_interest', ['first_name' => 'A', 'other_first_name' => 'B']),
            WhatsAppTemplates::order('mutual_interest', ['first_name' => 'B', 'other_first_name' => 'A']),
        );
    }

    /** A missing variable is named, rather than becoming a blank slot. */
    public function test_a_missing_variable_is_refused_by_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing: other_first_name');

        WhatsAppTemplates::order('mutual_interest', ['first_name' => 'Aisha']);
    }

    public function test_an_unexpected_variable_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unexpected: score');

        WhatsAppTemplates::order('someone_interested', ['first_name' => 'Aisha', 'score' => '92']);
    }

    /**
     * ⚠️ THE BODY IS SUBMITTED TO META, READ BY A REVIEWER, AND THEN SITS ON A
     * LOCK SCREEN. It is the most public surface in the product.
     */
    public function test_no_template_body_contains_banned_vocabulary(): void
    {
        foreach (WhatsAppTemplates::TEMPLATES as $name => $template) {
            $prose = $this->prose($template['body']);

            foreach (array_merge(ForbiddenLanguageTest::TIER_1, ForbiddenLanguageTest::TIER_2) as $term) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $term,
                    $prose,
                    "Banned term [{$term}] appears in the body of template [{$name}], which is "
                        .'submitted to Meta and displayed on a lock screen.'
                );
            }

            // The template NAME is submitted too, and is visible in the dashboard.
            foreach (ForbiddenLanguageTest::TIER_1 as $term) {
                $this->assertStringNotContainsStringIgnoringCase($term, $name);
            }
        }
    }

    /**
     * ⚠️ URLS ARE SCANNED SEPARATELY, AS WORDS RATHER THAN AS SUBSTRINGS.
     *
     * The substring scan above is correct for prose and wrong for a domain:
     * `realvows.skillleo.com` contains "leo", so a literal search flags the
     * developer's own hostname as a star sign. Widening the exception list
     * would be the easy fix and the wrong one — it switches the guard off for
     * whatever else that URL later contains.
     *
     * Instead the URL is removed from the prose scan and checked here against
     * whole words, so `skillleo` passes and `realvows.leo.com` would not.
     */
    public function test_no_url_in_a_template_contains_a_sign_name_as_a_word(): void
    {
        $signs = array_values(array_filter(
            ForbiddenLanguageTest::TIER_1,
            fn (string $term) => preg_match('/^[a-z]+$/', $term) === 1,
        ));

        $pattern = '/\b('.implode('|', array_map('preg_quote', $signs)).')\b/i';

        foreach (WhatsAppTemplates::TEMPLATES as $name => $template) {
            preg_match_all('#\bhttps?://\S+|\b[a-z0-9.-]+\.(?:com|io|app)\S*#i', $template['body'], $urls);

            foreach ($urls[0] as $url) {
                $this->assertDoesNotMatchRegularExpression(
                    $pattern,
                    $url,
                    "The URL [{$url}] in template [{$name}] contains a sign name as a word."
                );
            }
        }

        /*
         * ⚠️ POSITIVE CONTROL (Rule 5). Both readers are negative assertions
         * over text, so each must be shown finding what it looks for.
         */
        $this->assertMatchesRegularExpression($pattern, 'realvows.leo.com', 'The word reader cannot see a real sign name in a URL.');
        $this->assertDoesNotMatchRegularExpression($pattern, 'realvows.skillleo.com', 'The word reader flags the hostname it exists to allow.');

        $this->assertStringNotContainsString(
            'skillleo',
            $this->prose('Open it here: realvows.skillleo.com/dashboard'),
            'The URL stripper is not removing URLs, so the prose scan is reading hostnames.'
        );
        $this->assertStringContainsString(
            'Open it here',
            $this->prose('Open it here: realvows.skillleo.com/dashboard'),
            'The URL stripper is eating the sentence around the URL, which would blind the prose scan.'
        );
    }

    /** A body with its URLs removed — what a reader actually reads as language. */
    private function prose(string $body): string
    {
        return preg_replace('#\bhttps?://\S+|\b[a-z0-9.-]+\.(?:com|io|app)\S*#i', ' ', $body);
    }

    /**
     * ⚠️ NO SCORE IN A NOTIFICATION. A number on a lock screen is legible to
     * anyone holding the phone, including people the member has not told they
     * are looking for a husband or a wife.
     */
    public function test_no_template_body_carries_a_compatibility_score(): void
    {
        foreach (WhatsAppTemplates::TEMPLATES as $name => $template) {
            foreach (['score', 'compatib', '%', 'out of 100', 'match strength'] as $term) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $term,
                    $template['body'],
                    "Template [{$name}] puts a compatibility score on a lock screen. The score "
                        .'lives behind the link.'
                );
            }
        }
    }

    /**
     * ⚠️ ONLY ONE TEMPLATE MAY NAME ANOTHER MEMBER, AND ONLY BECAUSE BOTH
     * PEOPLE HAVE ALREADY AGREED.
     *
     * `someone_interested` in particular must carry exactly one variable — the
     * recipient's own name — because a second slot is where a well-meaning
     * change would put the admirer's name and disclose a one-sided interest.
     */
    public function test_only_mutual_interest_may_name_a_third_party(): void
    {
        foreach (WhatsAppTemplates::TEMPLATES as $name => $template) {
            if ($name === 'mutual_interest') {
                $this->assertContains('other_first_name', $template['variables']);

                continue;
            }

            $this->assertNotContains(
                'other_first_name',
                $template['variables'],
                "Template [{$name}] carries another member's name. Only mutual_interest may, and "
                    .'only because both people have consented by then.'
            );
        }

        $this->assertSame(
            ['first_name'],
            WhatsAppTemplates::variablesFor('someone_interested'),
            'someone_interested has gained a variable. It has exactly one — the recipient\'s own '
                .'name — because any second slot discloses a one-sided interest.'
        );
    }

    /** ⚠️ Meta rejects a body that starts or ends with a variable. */
    public function test_no_body_starts_or_ends_with_a_variable(): void
    {
        foreach (WhatsAppTemplates::TEMPLATES as $name => $template) {
            $body = trim($template['body']);

            $this->assertStringStartsNotWith('{{', $body, "Template [{$name}] opens with a variable; Meta rejects that.");
            $this->assertStringEndsNotWith('}}', $body, "Template [{$name}] ends with a variable; Meta rejects that.");
        }
    }

    /** Every placeholder the body uses must be declared, and vice versa. */
    public function test_the_declared_variables_match_the_placeholders_in_the_body(): void
    {
        foreach (WhatsAppTemplates::TEMPLATES as $name => $template) {
            preg_match_all('/\{\{(\d+)\}\}/', $template['body'], $found);

            /*
             * ⚠️ POSITIVE CONTROL (Rule 5). Without this the whole check
             * passes for a template with no placeholders AND for a reader
             * whose pattern has stopped matching — the two look identical from
             * here, and the second means the guard is switched off.
             */
            if ($template['variables'] !== []) {
                $this->assertNotEmpty(
                    $found[1],
                    "The placeholder reader found nothing in template [{$name}], which declares "
                        .count($template['variables']).' variables. Either the body lost its '
                        .'placeholders or the reader has stopped matching them.'
                );
            }

            $placeholders = array_map('intval', array_unique($found[1]));
            sort($placeholders);

            $this->assertSame(
                range(1, count($template['variables'])) === [] ? [] : range(1, count($template['variables'])),
                $placeholders,
                "Template [{$name}] declares ".count($template['variables'])
                    .' variables but its body uses placeholders ['.implode(', ', $placeholders).']. '
                    .'Meta rejects a send whose parameter count does not match the approved body.'
            );
        }
    }

    /**
     * Opting out must never be buried — every notification says how to stop.
     *
     * The authentication code is the one exception: a code the member just
     * asked for is not a notification, and Meta's authentication format does
     * not allow the extra line.
     */
    public function test_every_notification_body_ends_with_the_opt_out_line(): void
    {
        foreach (WhatsAppTemplates::TEMPLATES as $name => $template) {
            if ($template['category'] === WhatsAppTemplates::AUTHENTICATION) {
                $this->assertStringNotContainsString(WhatsAppTemplates::OPT_OUT, $template['body']);

                continue;
            }

            $this->assertStringEndsWith(
                WhatsAppTemplates::OPT_OUT,
                trim($template['body']),
                "Template [{$name}] does not end with the opt-out line."
            );
        }
    }

    /** ⚠️ The code template is AUTHENTICATION; submitted as utility it is rejected. */
    public function test_the_verification_code_is_an_authentication_template(): void
    {
        $this->assertSame(
            WhatsAppTemplates::AUTHENTICATION,
            WhatsAppTemplates::TEMPLATES['phone_verification_code']['category'],
        );

        foreach (WhatsAppTemplates::TEMPLATES as $name => $template) {
            if ($name === 'phone_verification_code') {
                continue;
            }

            $this->assertSame(
                WhatsAppTemplates::UTILITY,
                $template['category'],
                "Template [{$name}] is not Utility. Marketing costs more, approves slower, and is "
                    .'the wrong classification for a message the member\'s own action triggered.'
            );
        }
    }
}
