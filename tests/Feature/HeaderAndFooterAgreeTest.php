<?php

namespace Tests\Feature;

use App\Support\PublicState;
use Tests\TestCase;

/**
 * ⚠️ THE HEADER AND THE FOOTER MUST OFFER THE SAME PLATFORM — R10-C §6.
 *
 * On production they did not. The header showed How it works, About, Questions.
 * The footer showed How It Works, The Science, Packages, About, Questions.
 * Neither matched the other and neither matched the brief, because each held
 * its own copy of the list in its own file and whoever edited one did not know
 * about the other.
 *
 * Both now render PublicState::platformLinks(). This proves it, so the two
 * cannot drift apart again by somebody editing one template.
 */
class HeaderAndFooterAgreeTest extends TestCase
{
    private function linksIn(string $html, string $openTag): array
    {
        $start = strpos($html, $openTag);
        $this->assertNotFalse($start, "Could not find [{$openTag}] on the page.");

        $end = strpos($html, '</nav>', $start);
        $this->assertNotFalse($end, 'The navigation element is not closed.');

        preg_match_all(
            '/href="([^"]+)"[^>]*>\s*([^<]+?)\s*</',
            substr($html, $start, $end - $start),
            $found
        );

        return $found[2];
    }

    public function test_the_header_and_footer_offer_the_same_platform_links(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $expected = array_map(fn ($l) => $l['label'], PublicState::platformLinks());

        $header = $this->linksIn($html, '<nav class="rv-pubhead__nav rv-pubhead__nav--wide"');
        $footer = $this->linksIn($html, '<nav class="rv-pubfoot__col" aria-label="Platform"');

        // The header also carries Sign in and Join free, which are actions.
        $headerPlatform = array_values(array_intersect($header, $expected));

        $this->assertSame(
            $expected,
            $headerPlatform,
            'The header does not offer the platform links in the order they are declared.'
        );

        $this->assertSame(
            $expected,
            array_values($footer),
            'The footer does not match the header. Both must render PublicState::platformLinks().'
        );
    }

    /** ⚠️ CONTROL — the comparison must be able to see a difference. */
    public function test_the_comparison_can_see_a_mismatch(): void
    {
        $a = ['How it works', 'The Science', 'Packages'];
        $b = ['How it works', 'About', 'Questions'];

        $this->assertNotSame($a, $b, 'The comparison cannot distinguish two different link sets.');
    }

    /** ⚠️ Nothing in either may point at a route a signed-out visitor cannot read. */
    public function test_no_platform_link_points_at_a_gated_route(): void
    {
        foreach (PublicState::platformLinks() as $link) {
            $path = parse_url($link['href'], PHP_URL_PATH) ?: '/';

            $this->assertSame(
                200,
                $this->get($path)->getStatusCode(),
                "Platform link [{$link['label']}] points at {$path}, which a signed-out visitor cannot read."
            );
        }
    }
}
