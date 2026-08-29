<?php

namespace Tests\Feature;

use App\Support\ReviewBuild;
use Tests\TestCase;

/**
 * ⚠️ THE REVIEW TREATMENT MAY NOT APPEAR ON THE CLIENT'S OWN DOMAIN — R10-C §1.
 *
 * On 2026-08-29 realvows.com was serving all three parts of the review build:
 *
 *   · a banner reading "This is not the live platform. Every member shown here
 *     is invented, and the sign-in buttons are open to anyone."
 *   · <meta name="robots" content="noindex, nofollow, noarchive">
 *   · robots.txt containing Disallow: /
 *
 * The server's .env still said APP_ENV=demo from when the same build was a
 * staging site. The banner was the visible half; the invisible half was that
 * the client's live business had been telling every search engine not to index
 * it for as long as it had been up.
 *
 * The banner's own claim was also false: the one-click logins hang off a
 * separate flag and were off. So the page was telling visitors the product was
 * fake, and telling Google to ignore it, for no reason at all.
 */
class ReviewBannerNeverReachesProductionTest extends TestCase
{
    private const BANNER = 'This is not the live platform';

    /** ⚠️ CONTROL — a review host with the flag on MUST show all three. */
    public function test_a_review_host_still_carries_the_review_treatment(): void
    {
        config(['realvows.is_demo' => true]);

        $this->assertTrue(
            ReviewBuild::active('staging.example.test'),
            'The review treatment no longer appears anywhere. The guard is inverted and the '
                .'banner that stops somebody mistaking a demo for the product is gone.'
        );

        // ⚠️ AN ABSOLUTE URL, NOT AN HTTP_HOST HEADER. Passing HTTP_HOST in the
        // header array makes a header literally named HTTP_HOST; it does not set
        // the request's host, so every assertion below ran against localhost and
        // this guard proved nothing.
        $html = $this->get('https://staging.example.test/')->getContent();
        $this->assertStringContainsString(self::BANNER, $html);
        $this->assertStringContainsString('noindex', $html);
    }

    /** ⚠️ CONTROL — the same flag on a production host MUST show none of it. */
    public function test_a_production_host_never_carries_it_even_with_the_flag_on(): void
    {
        config(['realvows.is_demo' => true]);

        foreach (ReviewBuild::productionHosts() as $host) {
            $this->assertFalse(
                ReviewBuild::active($host),
                "[{$host}] is a production hostname and the review treatment resolved active on it."
            );

            $html = $this->get("https://{$host}/")->getContent();

            $this->assertStringNotContainsString(
                self::BANNER,
                $html,
                "[{$host}] is telling visitors it is not the live platform."
            );

            $this->assertStringNotContainsString(
                'noindex',
                $html,
                "[{$host}] is telling search engines not to index the client's business."
            );
        }
    }

    /** robots.txt must not disallow the whole site on a production host. */
    public function test_robots_does_not_disallow_everything_on_production(): void
    {
        config(['realvows.is_demo' => true]);

        foreach (ReviewBuild::productionHosts() as $host) {
            $body = $this->get("https://{$host}/robots.txt")->assertOk()->getContent();

            $this->assertStringNotContainsString(
                "Disallow: /\n",
                $body,
                "[{$host}] robots.txt disallows the entire site. Nothing can be found on Google."
            );
        }
    }

    /** And the review host's robots.txt must still lock it down. */
    public function test_robots_still_disallows_everything_on_a_review_host(): void
    {
        config(['realvows.is_demo' => true]);

        $this->assertStringContainsString(
            "Disallow: /\n",
            $this->get('https://staging.example.test/robots.txt')->getContent(),
            'A review build is indexable. It has open sign-in and invented members on it.'
        );
    }

    /** The production host list must not be empty, or every check above is vacuous. */
    public function test_the_production_host_list_is_populated(): void
    {
        $hosts = ReviewBuild::productionHosts();

        $this->assertNotEmpty($hosts, 'No production hostnames are configured, so nothing is protected.');
        $this->assertContains('realvows.com', $hosts);
    }

    /**
     * ⚠️ THE BANNER GOING QUIET MUST NEVER OPEN A DOOR.
     *
     * The one-click logins hang off a different flag on purpose. If they were
     * ever moved onto this one, silencing the banner would silently register
     * the routes.
     */
    public function test_the_one_click_logins_do_not_follow_this_flag(): void
    {
        $this->assertFalse(
            config('realvows.demo_logins'),
            'Demo logins are enabled in the test environment; this assertion cannot prove anything.'
        );

        config(['realvows.is_demo' => true]);

        $this->assertFalse(
            config('realvows.demo_logins'),
            'The one-click logins are keyed to the review-banner flag. Silencing the banner would '
                .'open every account to a stranger.'
        );
    }
}
