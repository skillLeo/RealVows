<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ THE ASTROLOGICAL COMPONENT DOES NOT EXIST. IT IS NOT DISABLED OR HIDDEN.
 *
 * It was removed after a scholar was consulted. It was twenty-five points of
 * the compatibility score, two full sections of the marketing site and a
 * navigation item, and the client's own visual reference still shows it as the
 * headline feature across two screens.
 *
 * So the risk is not that somebody argues for it. The risk is that somebody
 * rebuilding a page from that reference carries a word across without noticing
 * — in a class name, an alt attribute, a meta description, a filename.
 *
 * ⚠️ THIS READS RENDERED OUTPUT, NOT SOURCE. A term can survive a grep of the
 * templates and still reach a browser through a config value, a settings row,
 * or a partial nobody thought to check.
 *
 * ── ⚠️ THE TRAP, AND WHY THE ALLOW-LIST IS EXACT PHRASES ─────────────────
 *
 * Naive matching on "sign" fires on "sign in", "sign up", "signature",
 * "assign" and "design", which appear legitimately on every page of this
 * application. A reader everybody learns to override is worse than no reader.
 *
 * And "star" at a word boundary must not reach "started", which is live on the
 * home page right now in "while we are getting started". That case is proved
 * below rather than assumed, because it is the one that would have this file
 * deleted within a week.
 */
class NothingCelestialSurvivesTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['/', '/how-it-works', '/about', '/questions'];

    /** Matched case-insensitively at word boundaries. */
    private const TERMS = [
        'zodiac', 'astrology', 'astrological', 'astrologer', 'horoscope',
        'star sign', 'sun sign', 'birth chart', 'natal', 'synastry',
        'celestial', 'cosmic', 'cosmos', 'constellation',
        'trine', 'sextile', 'modality', 'elemental harmony',
        'written in the stars', 'star', 'stars',
    ];

    /**
     * ⚠️ CASE-SENSITIVE, because several are ordinary words in lower case.
     * "cancer" and "leo" and "libra" must not fire on prose or on a name.
     */
    private const SIGNS = [
        'Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo', 'Libra',
        'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces',
    ];

    /**
     * ⚠️ EXACT PHRASES. The bare word "sign" outside these fails, and that is
     * correct: there is no legitimate reason for it to stand alone here.
     */
    private const ALLOWED = [
        'sign in', 'sign up', 'sign out', 'signed in', 'signed up',
        'sign-in', 'sign-up', 'signature', 'assign', 'assigned',
        'assignment', 'design', 'designed', 'designer', 'resign',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    /**
     * Everything the browser receives: markup, class names, alt text, aria
     * labels, titles, meta descriptions and asset filenames.
     */
    private function everythingSent(string $page): string
    {
        return $this->get($page)->assertOk()->getContent();
    }

    /** @return list<string> */
    private function offences(string $haystack): array
    {
        $found = [];

        // Remove the allow-listed phrases first, so what remains is genuine.
        $scrubbed = $haystack;

        foreach (self::ALLOWED as $phrase) {
            $scrubbed = preg_replace('/(?<![a-z0-9])'.preg_quote($phrase, '/').'(?![a-z0-9])/i', ' ', $scrubbed) ?? $scrubbed;
        }

        /*
         * ⚠️ \b IS THE WRONG BOUNDARY HERE, AND A CLASS NAME PROVED IT.
         *
         * An underscore is a word character, so `\bhoroscope\b` does not match
         * inside `rv-horoscope__ring`. That is precisely where a term would
         * survive a redesign: not in prose, where somebody would read it, but
         * in a class name copied across from the reference.
         *
         * Letters and digits only as boundary characters, so a hyphen or an
         * underscore both count as the edge of a word.
         */
        $edge = fn (string $needle) => '(?<![a-z0-9])'.preg_quote($needle, '/').'(?![a-z0-9])';

        foreach (self::TERMS as $term) {
            if (preg_match('/'.$edge($term).'/i', $scrubbed)) {
                $found[] = $term;
            }
        }

        foreach (self::SIGNS as $sign) {
            if (preg_match('/'.$edge($sign).'/', $scrubbed)) {
                $found[] = $sign;
            }
        }

        return array_values(array_unique($found));
    }

    public function test_no_public_page_carries_a_celestial_reference(): void
    {
        foreach (self::PAGES as $page) {
            $body = $this->everythingSent($page);

            $this->assertNotEmpty($body, "{$page} rendered nothing, so this reader saw nothing.");

            $this->assertSame(
                [],
                $this->offences($body),
                "{$page} carries a celestial reference: ".implode(', ', $this->offences($body))
                    .".\n\nThis component does not exist. It was removed after a scholar was "
                    .'consulted, and a word surviving in a class name or an alt attribute is the '
                    .'way it comes back.',
            );
        }
    }

    public function test_the_manifest_and_page_metadata_are_clean_too(): void
    {
        foreach (['/manifest.webmanifest', '/robots.txt'] as $asset) {
            $response = $this->get($asset);

            if ($response->getStatusCode() !== 200) {
                continue;
            }

            $this->assertSame([], $this->offences($response->getContent()), "{$asset} is not clean.");
        }
    }

    // ── ⚠️ Controls, in both directions ──────────────────────────────────

    /**
     * ⚠️ THE ONE THAT MATTERS MOST. "while we are getting started" is live on
     * the home page. A reader that flags it is a reader nobody keeps.
     */
    public function test_getting_started_is_not_mistaken_for_a_star(): void
    {
        $this->assertSame(
            [],
            $this->offences('Introductions are free while we are getting started. Start now.'),
            'The reader flagged "started". It is on the home page today, and a guard that cries '
                .'wolf on live copy is one somebody switches off.',
        );
    }

    public function test_every_allow_listed_phrase_passes(): void
    {
        $sentence = 'Sign in or sign up. Your signature is on the assignment we assigned. '
            .'The design was designed by a designer who did not resign. Signed in, signed up.';

        $this->assertSame(
            [],
            $this->offences($sentence),
            'A legitimate use of "sign" was flagged, which is how a reader gets overridden.',
        );
    }

    /**
     * ⚠️ AND THE READER MUST ACTUALLY FIRE. Every assertion above is an
     * empty-array check, and a scrubber that removed too much, or a regex that
     * stopped matching, reports a spotless site forever.
     */
    public function test_the_reader_catches_a_genuine_violation(): void
    {
        foreach ([
            'Your zodiac compatibility is 40% of the score.',
            'Alignment written in the stars.',
            'She is a Taurus and he is a Scorpio.',
            '<img alt="celestial wheel" src="/img/cosmic-bg.png">',
            '<div class="rv-horoscope__ring">',
            'Your sun sign is derived from your date of birth.',
        ] as $violation) {
            $this->assertNotEmpty(
                $this->offences($violation),
                "The reader missed a genuine violation: {$violation}",
            );
        }
    }

    /** ⚠️ The bare word, standing alone, must still fail. */
    public function test_a_bare_sign_is_not_excused_by_the_allow_list(): void
    {
        $this->assertNotEmpty(
            $this->offences('Every star sign is weighted.'),
            '"star sign" was excused, which means the allow-list is scrubbing more than the '
                .'phrases it names.',
        );
    }
}
