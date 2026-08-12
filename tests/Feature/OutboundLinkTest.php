<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicSurfaces;
use Tests\Support\Scanners;
use Tests\TestCase;

/**
 * ⚠️ NO USER-FACING SURFACE MAY POINT AT A HOST WE DO NOT CONTROL.
 *
 * ── Why this exists ───────────────────────────────────────────────────────
 *
 * The live marketing page carried `realvows.com/m/8f2a…` inside a mock-up for
 * weeks. We do not own that domain. Nobody noticed, because it looked exactly
 * like the thing it was pretending to be and nothing in the build had an
 * opinion about hostnames.
 *
 * It reads as a typo. It is not. A domain that appears in a real product's
 * marketing is precisely the signal a squatter watches for, and a squatter who
 * registers it gains a link from the client's own site to whatever they choose
 * to put there — served under a name members already associate with us. The
 * window between "we published it" and "someone claimed it" is the whole risk,
 * and it had been open since the page went live.
 *
 * ── ⚠️ AN ALLOW-LIST, AND A SHORT ONE ─────────────────────────────────────
 *
 * When the client buys his own domain this gains ONE entry. That is the design:
 * adding a host should be a deliberate line in a diff somebody has to justify,
 * not something that happens because a copywriter pasted a URL.
 */
class OutboundLinkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ⚠️ EVERY HOST ANY MEMBER-FACING SURFACE IS ALLOWED TO NAME.
     *
     * Each entry carries its reason. An entry without one is how an allow-list
     * turns into an ignore-list.
     */
    private const ALLOWED = [
        /*
         * ⚠️ THE DOMAIN THIS GUARD WAS WRITTEN ABOUT IS NOW OURS.
         *
         * `realvows.com` appeared on the live marketing page inside a mock-up,
         * pointing at a domain nobody involved owned, and this test exists
         * because of it. The client has since bought it. That is the best
         * possible outcome and it is also exactly the change this list was
         * designed to absorb: one line, with a reason.
         */
        'realvows.com',
        'www.realvows.com',

        // The staging host. Six WhatsApp templates still name it and they are
        // in review with Meta, so it stays resolvable until they are resubmitted.
        'realvows.skillleo.com',

        // The XML namespace on inline SVG. Declared, never fetched, never
        // navigable — but it is a hostname in the output and pretending
        // otherwise would mean teaching the reader an exception.
        'www.w3.org',

        // IANA reserves example.com precisely so documentation and form
        // placeholders can name a domain nobody can ever register. A
        // `placeholder="you@example.com"` is the one safe way to show the
        // shape of an email address.
        'example.com',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    /**
     * The app's own host under test, which is whatever APP_URL says rather
     * than a string typed here — otherwise this fails on any machine
     * configured differently, and the fix somebody reaches for is to widen the
     * allow-list.
     */
    private function allowed(): array
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return array_values(array_filter([...self::ALLOWED, $appHost]));
    }

    public function test_no_public_page_links_to_a_host_we_do_not_own(): void
    {
        $offences = [];

        foreach (PublicSurfaces::paths() as $path) {
            $response = $this->get($path);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "{$path} did not render, so this guard read nothing for it."
            );

            foreach ($this->strayHosts($response->getContent()) as $host) {
                $offences[] = "{$path} points at {$host}";
            }
        }

        $this->assertSame([], $offences, $this->explain($offences));
    }

    /**
     * ⚠️ THE WHATSAPP BODIES CARRY BARE HOSTS AS TEXT, and a bare host is a
     * link the moment it lands on a phone — WhatsApp linkifies it.
     */
    public function test_no_whatsapp_template_names_a_host_we_do_not_own(): void
    {
        $offences = [];

        foreach (PublicSurfaces::whatsAppBodies() as $label => $body) {
            foreach ($this->strayHosts($body) as $host) {
                $offences[] = "{$label} names {$host}";
            }
        }

        $this->assertSame([], $offences, $this->explain($offences));
    }

    public function test_the_manifest_names_no_host_we_do_not_own(): void
    {
        $offences = [];

        foreach ($this->strayHosts(PublicSurfaces::manifest()) as $host) {
            $offences[] = "manifest.webmanifest names {$host}";
        }

        $this->assertSame([], $offences, $this->explain($offences));
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5), BOTH DIRECTIONS.
     *
     * The planted host is the exact one that shipped. The clean sample is a
     * real fragment of our own markup — a reader that flags it would fail the
     * build on every page and be switched off within a day, which is the
     * over-eager half of the same failure.
     */
    public function test_the_reader_sees_a_stray_host_and_leaves_our_own_alone(): void
    {
        /*
         * ⚠️ THE PLANTED HOST CHANGED, AND THE CONTROL IS WHY WE NOTICED.
         *
         * This used to plant `realvows.com`, the domain that actually shipped
         * on the live page. The client has since bought it, so it moved to the
         * allow-list and the control immediately failed: the thing it plants
         * was no longer catchable. That is precisely the job. A control that
         * had quietly kept passing would have been vouching for nothing.
         *
         * Replanted with a near-miss nobody here owns.
         */
        $planted = '<span class="rv-arrives__url">realvows.net/m/8f2a…</span>';
        $clean = '<a href="https://realvows.skillleo.com/dashboard">Open</a>'
            .'<svg xmlns="http://www.w3.org/2000/svg"></svg>'
            .'<input placeholder="you@example.com">';

        $this->assertSame(
            ['realvows.net'],
            $this->strayHosts($planted),
            'The reader cannot see the host that actually shipped.'
        );

        $this->assertSame(
            [],
            $this->strayHosts($clean),
            'The reader flags our own markup, which would make it noise.'
        );
    }

    /** @return list<string> */
    private function strayHosts(string $text): array
    {
        $allowed = $this->allowed();

        return array_values(array_filter(
            Scanners::hosts($text),
            fn (string $host) => ! in_array($host, $allowed, true),
        ));
    }

    private function explain(array $offences): string
    {
        return "A user-facing surface points at a host outside the allow-list. If this is a "
            ."domain we have just bought, add it to OutboundLinkTest::ALLOWED with the reason. "
            ."If it is not, it is a link from the client's site to somebody else's "
            ."property:\n  ".implode("\n  ", $offences);
    }
}
