<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ FOUR DIFFERENT ENDINGS ON ONE ROW OF CARDS IS SOMETHING A READER FEELS
 * WITHOUT BEING ABLE TO NAME IT.
 *
 * One description ends with a full stop, the next does not, the third ends with
 * a colon. Nobody reports it and everybody registers it as the page being
 * slightly amateur. It is the cheapest possible guard and it has already earned
 * its place once.
 */
class CardDescriptionsEndAlikeTest extends TestCase
{
    use RefreshDatabase;

    /*
     * ⚠️ QUESTIONS IS ABSENT ON PURPOSE AND IT IS NOT AN OVERSIGHT.
     *
     * It is built from native <details> elements rather than cards, so there is
     * no row of descriptions to keep consistent. Listing it here made the
     * reader assert against an empty set, which the extraction proof caught
     * immediately — the guard was claiming coverage it did not have.
     */
    private const PAGES = ['/', '/how-it-works', '/about'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    /** @return list<string> The body paragraph of every card on a page. */
    private function cardDescriptions(string $page, bool $allowEmpty = false): array
    {
        $html = $this->get($page)->assertOk()->getContent();

        preg_match_all(
            '/<p class="rv-body"[^>]*>(.*?)<\/p>/s',
            $html,
            $found,
        );

        /*
         * ⚠️ PROVED WHERE IT IS EXTRACTED, NOT ONLY WHERE IT IS USED. The
         * caller skips a page with fewer than two descriptions, so a selector
         * that matched nothing would skip every page and report a clean site.
         */
        if (! $allowEmpty) {
            $this->assertNotEmpty(
                $found[1],
                "No card description was found on {$page}. The selector is reading the wrong "
                    .'element, so any verdict about punctuation is a verdict about nothing.',
            );
        }

        return array_values(array_filter(array_map(
            fn ($raw) => trim(preg_replace('/\s+/', ' ', strip_tags($raw)) ?? ''),
            $found[1],
        )));
    }

    public function test_card_descriptions_on_a_page_end_the_same_way(): void
    {
        foreach (self::PAGES as $page) {
            $descriptions = $this->cardDescriptions($page);

            if (count($descriptions) < 2) {
                continue;
            }

            $endings = array_unique(array_map(
                fn (string $d) => substr($d, -1),
                $descriptions,
            ));

            $this->assertCount(
                1,
                $endings,
                "{$page} has card descriptions ending "
                    .count($endings)." different ways: ".implode(' ', $endings)
                    ."\n\n  ".implode("\n  ", array_map(fn ($d) => '…'.substr($d, -48), $descriptions)),
            );
        }
    }

    /**
     * ⚠️ THE EXTRACTION IS PROVED. Every assertion above skips a page with
     * fewer than two descriptions, so a selector that matched nothing would
     * skip every page and report a spotless site.
     */
    public function test_the_reader_finds_the_descriptions_it_is_judging(): void
    {
        $onHome = $this->cardDescriptions('/');

        $this->assertGreaterThanOrEqual(
            5,
            count($onHome),
            'Only '.count($onHome).' card descriptions were found on the home page. The reader '
                .'is looking at the wrong element and its silence means nothing.',
        );
    }

    /**
     * ⚠️ AND QUESTIONS REALLY IS BUILT THE OTHER WAY.
     *
     * If it ever gains a row of cards, this fails and somebody adds it to the
     * list above rather than the page quietly going unchecked.
     */
    public function test_questions_is_still_built_from_details_rather_than_cards(): void
    {
        $html = $this->get('/questions')->assertOk()->getContent();

        $this->assertStringContainsString('<details', $html);
        $this->assertSame(
            [],
            $this->cardDescriptions('/questions', allowEmpty: true),
            'The questions page now has card descriptions. Add it to PAGES so they are checked.',
        );
    }

    /** ⚠️ And it must be able to see a mismatch. */
    public function test_the_reader_would_catch_a_mismatch(): void
    {
        $mixed = ['One ends properly.', 'This one does not', 'And this one:'];

        $endings = array_unique(array_map(fn ($d) => substr($d, -1), $mixed));

        $this->assertGreaterThan(1, count($endings));
        $this->assertCount(1, array_unique(array_map(
            fn ($d) => substr($d, -1),
            ['Alike.', 'Also alike.'],
        )));
    }
}
