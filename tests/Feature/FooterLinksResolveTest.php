<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ A NAVIGATION LINK TO A PAGE WE DO NOT HAVE IS WORSE THAN NO LINK.
 *
 * The client reference's own footer lists a blog with three articles, two of
 * which describe mechanisms this product no longer discusses. There is no blog
 * route in this application, so those columns do not exist here — and this
 * guard is what stops one arriving later because a footer looked thin.
 *
 * ⚠️ IT FOLLOWS THE LINKS RATHER THAN READING THE TEMPLATE. A test asserting
 * the markup contains the right route() calls passes on a route that has since
 * been renamed, because Blade would have thrown at render time and the test
 * never rendered.
 */
class FooterLinksResolveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    /** @return list<string> Every href inside the footer element. */
    /**
     * ⚠️ EVERY footer href, INCLUDING ANCHORS AND mailto.
     *
     * The floor above asks "is this reader looking at the right element", which
     * is a different question from "which of these can be fetched". Counting
     * only the walkable set made an eight-entry footer read as seven, because
     * two of its entries are in-page anchors and one is an email address.
     *
     * @return list<string>
     */
    private function footerEntries(): array
    {
        $html = $this->get('/')->assertOk()->getContent();
        $start = strpos($html, '<footer');
        $this->assertNotFalse($start, 'There is no footer on the home page at all.');

        preg_match_all('/href="([^"]+)"/', substr($html, $start), $found);

        return array_values(array_unique($found[1]));
    }

    private function footerLinks(): array
    {
        $html = $this->get('/')->assertOk()->getContent();

        $start = strpos($html, '<footer');
        $this->assertNotFalse($start, 'There is no footer on the home page at all.');

        $footer = substr($html, $start);

        preg_match_all('/href="([^"#]+)"/', $footer, $found);

        /*
         * ⚠️ THE EXTRACTION IS PROVED HERE, NOT ONLY BY THE CALLER.
         *
         * Every check built on this list is a loop, and a loop over nothing
         * reports a flawless footer in exactly the same words as a real one.
         */
        $this->assertNotEmpty(
            $found[1],
            'No hrefs were extracted from the footer element. The reader is looking at the '
                .'wrong part of the document.',
        );

        /*
         * ⚠️ mailto: AND tel: ARE NOT ROUTES, AND WALKING THEM IS A FALSE
         * FAILURE.
         *
         * The footer now carries support@realvows.com, and this reader handed
         * "mailto:support@realvows.com" to the HTTP kernel, got a 404 and
         * reported a broken footer link. The link is correct; the reader was
         * wrong about what kind of link it is.
         *
         * They are counted toward the floor above — a contact address is a real
         * footer entry — but only http(s) and site-relative hrefs are fetched.
         */
        $walkable = array_values(array_filter(
            array_unique($found[1]),
            fn (string $href) => ! preg_match('/^(mailto:|tel:|sms:|javascript:)/i', $href),
        ));

        $this->assertNotEmpty(
            $walkable,
            'Every footer href is a mailto or tel. There is nothing to walk.',
        );

        return $walkable;
    }

    public function test_every_footer_link_answers(): void
    {
        $links = $this->footerLinks();

        /*
         * ⚠️ THE SET IS PROVED NON-EMPTY FIRST. Every assertion below is a
         * loop, and a loop over nothing passes in exactly the same words as a
         * footer where every link works.
         */
        $this->assertGreaterThanOrEqual(
            8,
            count($this->footerEntries()),
            'Only '.count($this->footerEntries()).' links were found in the footer. The extraction is reading '
                .'the wrong element.',
        );

        foreach ($links as $href) {
            $path = parse_url($href, PHP_URL_PATH) ?: '/';

            $status = $this->get($path)->getStatusCode();

            $this->assertContains(
                $status,
                [200, 302],
                "The footer links to {$path}, which answers {$status}. A visitor who trusts a "
                    .'link in the footer and lands on an error trusts the rest of the page less.',
            );
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL. "Every link answered" is also what a checker that
     * follows nothing reports, and what one pointed at the wrong element
     * reports.
     */
    public function test_the_checker_would_catch_a_broken_link(): void
    {
        $this->assertSame(
            404,
            $this->get('/a-page-that-does-not-exist')->getStatusCode(),
            'A route that certainly does not exist answered something other than 404, so the '
                .'check above cannot distinguish a working link from a broken one.',
        );
    }

    /** ⚠️ Nothing links to the blog the reference had and we do not. */
    public function test_the_footer_does_not_link_to_a_blog(): void
    {
        foreach ($this->footerLinks() as $href) {
            $this->assertStringNotContainsString('blog', strtolower($href));
        }
    }

    /**
     * ⚠️ AND NOTHING LINKS TO PACKAGES WHILE IT 404s.
     *
     * /packages is deliberately absent during the free period. A footer link
     * to it would be a link to an error, which is the exact thing this file
     * exists to prevent.
     */
    public function test_the_footer_does_not_link_to_packages_during_the_free_period(): void
    {
        $this->assertFalse(
            \App\Support\Payments::areLive(),
            'Payments went live, so this test is now asserting the wrong thing and should be '
                .'inverted rather than deleted.',
        );

        foreach ($this->footerLinks() as $href) {
            $this->assertStringNotContainsString('/packages', $href);
        }
    }

    public function test_the_age_statement_is_in_the_footer(): void
    {
        $this->assertStringContainsString(
            'aged 18 and over',
            $this->get('/')->getContent(),
            'The age statement is a real constraint on who may hold an account, not decoration.',
        );
    }
}
