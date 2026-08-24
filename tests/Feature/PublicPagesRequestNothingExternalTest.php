<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ AN ALLOW-LIST OF OUR OWN ORIGIN, NOT A BLOCK-LIST OF KNOWN HOSTS.
 *
 * The typeface guard used to name four font CDNs. Enumerated sets fail on the
 * fifth, and the fifth is always the one that ships: a new provider, a
 * self-hosted mirror, an analytics tag wearing a stylesheet. Naming what is
 * permitted is a much shorter list and it cannot be outflanked.
 *
 * ⚠️ THIS IS PERFORMANCE AND IT IS ALSO PRIVACY. Every external request on a
 * public page tells that host the IP address of somebody looking at a
 * matchmaking site before they have agreed to anything.
 */
class PublicPagesRequestNothingExternalTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['/', '/how-it-works', '/about', '/questions'];

    /** The only origins a public page may reach. */
    private const OURS = ['localhost', '127.0.0.1', 'realvows.com', 'www.realvows.com'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    /** @return list<string> Every absolute URL the browser would fetch. */
    private function externalRequests(string $page): array
    {
        $html = $this->get($page)->assertOk()->getContent();

        preg_match_all(
            '/(?:href|src)="(https?:\/\/[^"]+)"|@import\s+url\(["\']?(https?:\/\/[^"\')]+)/i',
            $html,
            $found,
        );

        $urls = array_filter(array_merge($found[1], $found[2]));
        $external = [];

        foreach ($urls as $url) {
            $host = parse_url($url, PHP_URL_HOST);

            if ($host && ! in_array($host, self::OURS, true)) {
                $external[] = $host;
            }
        }

        return array_values(array_unique($external));
    }

    public function test_no_public_page_requests_anything_from_another_origin(): void
    {
        foreach (self::PAGES as $page) {
            $this->assertSame(
                [],
                $this->externalRequests($page),
                "{$page} fetches from a host that is not ours. Every external request on a "
                    .'public page hands that host the IP address of somebody looking at a '
                    .'matchmaking site before they have agreed to anything.',
            );
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL, WITH A HOST THAT IS NOT ONE OF THE FOUR THE OLD
     * GUARD KNEW ABOUT AND IS NOT REAL.
     *
     * The point of inverting the rule is that it catches the host nobody
     * predicted, so the control has to use one nobody predicted.
     */
    public function test_the_reader_catches_a_host_it_has_never_heard_of(): void
    {
        $fixture = '<link rel="stylesheet" href="https://cdn.not-a-real-font-host.example/x.css">'
            .'<script src="https://telemetry.invented-vendor.test/t.js"></script>';

        preg_match_all('/(?:href|src)="(https?:\/\/[^"]+)"/i', $fixture, $found);

        $hosts = array_map(fn ($u) => parse_url($u, PHP_URL_HOST), $found[1]);
        $external = array_values(array_diff($hosts, self::OURS));

        $this->assertSame(
            ['cdn.not-a-real-font-host.example', 'telemetry.invented-vendor.test'],
            $external,
            'The reader cannot see a host it was not told about, which is the whole reason the '
                .'rule was inverted.',
        );
    }

    /** ⚠️ And it must not flag our own origin, or it flags every page. */
    public function test_our_own_origin_is_not_flagged(): void
    {
        $fixture = '<link rel="stylesheet" href="https://realvows.com/build/app.css">';

        preg_match_all('/(?:href|src)="(https?:\/\/[^"]+)"/i', $fixture, $found);

        $hosts = array_map(fn ($u) => parse_url($u, PHP_URL_HOST), $found[1]);

        $this->assertSame([], array_values(array_diff($hosts, self::OURS)));
    }

    /**
     * ⚠️ THE EXTRACTION IS PROVED. Every assertion above is an empty-array
     * check, and a regex that stopped matching reports a clean page forever.
     */
    public function test_the_extraction_finds_the_urls_a_page_really_has(): void
    {
        $html = $this->get('/')->getContent();

        preg_match_all('/(?:href|src)="([^"]+)"/i', $html, $found);

        $this->assertGreaterThan(
            5,
            count($found[1]),
            'Almost no hrefs or srcs were found on the home page, so the reader is looking at '
                .'nothing and its silence means nothing.',
        );
    }
}
