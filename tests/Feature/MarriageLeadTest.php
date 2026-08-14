<?php

namespace Tests\Feature;

use App\Services\Matching\Copy\RationaleWriter;
use App\Support\ProfileOptions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicSurfaces;
use Tests\Support\Scanners;
use Tests\TestCase;

/**
 * ⚠️ MARRIAGE IS THE DESTINATION. IT MAY NOT BE THE DOOR.
 *
 * ── The constraint, in the client's words ─────────────────────────────────
 *
 * "Even though it says RealVows, what you have told the AI is that it is just
 * about marriage. But the word you want to use is not marriage, because
 * marriage frightens a lot of people. It is more about finding your soul mate
 * — that resonates especially with women and also with men."
 *
 * ⚠️ HE DID NOT BAN THE WORD, and a guard that banned it would be wrong. His
 * own approved sentence for the legal pages ends "…and a lasting relationship,
 * leading to marriage." He DEMOTED it: soul mate is the frame and the first
 * thing anyone reads; marriage is where this is going, named late and softly,
 * in a small number of places.
 *
 * ── Why an allow-list rather than a positional rule alone ─────────────────
 *
 * "Not in the first sentence" is easy to check and easy to satisfy by moving a
 * word one sentence down. The register drifts back one paragraph at a time and
 * every individual step passes. So the list below is exhaustive: every
 * sentence on a user-facing surface that contains the vocabulary is named
 * here, with the reason it is allowed to.
 *
 * ⚠️ IT FAILS IN BOTH DIRECTIONS, and both halves are load-bearing:
 *
 *   · A sentence appearing that is NOT on the list fails. That is a third
 *     location gaining the phrasing — the drift this exists to catch.
 *
 *   · A listed sentence that no longer appears anywhere ALSO fails. An
 *     allow-list that outlives its subject is how a guard goes quietly blind:
 *     it keeps passing while permitting nothing, and nobody can tell it apart
 *     from a working one.
 *
 * ── ⚠️ IT READS RENDERED OUTPUT, NOT SOURCE ──────────────────────────────
 *
 * CommonalityFinder and RationaleWriter emit whole sentences that exist in no
 * template file. A source reader would report a clean repository and be wrong.
 */
class MarriageLeadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ⚠️ EVERY PERMITTED SENTENCE, AND WHERE IT MAY APPEAR.
     *
     * Keyed by a distinctive fragment of the sentence. `surfaces` is the list
     * of places it is allowed on — naming them means the same approved
     * sentence appearing somewhere new still fails, which is the difference
     * between an allow-list and a word filter.
     *
     * @var array<string, array{reason: string, surfaces: list<string>}>
     */
    private const ALLOWED = [
        'Their synergy will help them develop that bond' => [
            'reason' => "The client's own sentence, supplied verbatim and approved by him. "
                .'Marriage is the last word of a paragraph that opens on soul mate — this is '
                .'the register the whole repositioning is modelled on.',
            'surfaces' => ['/terms', '/privacy', '/community-guidelines', '/refunds'],
        ],

        // Case-insensitive by construction: the fragment starts mid-sentence so
        // it matches whether the clause opens the sentence or closes one.
        'can promise you a marriage' => [
            'reason' => 'The honest-limits clause in the refund policy. It exists to say what '
                .'we are NOT selling, and softening the word here would make the sentence '
                .'stop doing its job.',
            'surfaces' => ['/refunds'],
        ],

        /*
         * ⚠️ ALL THREE PURPOSE OPTIONS NAME MARRIAGE NOW, AND THE CLIENT ASKED
         * FOR THAT EXPLICITLY.
         *
         * Two of them used to say only "my soul mate", which left marriage
         * reading as one option among several — as though somebody could choose
         * this platform for something short of it. In his words: the purpose of
         * this site is finding couples for marriage, not dating or talking, so
         * we should not offer another option instead of marriage.
         *
         * ⚠️ SOUL MATE STILL LEADS IN EVERY ONE OF THEM, which is the rule this
         * file exists to hold. Marriage is the second clause, never the opening
         * word, so both instructions are satisfied at once rather than one
         * replacing the other.
         *
         * These are on a private, authenticated screen a member fills in about
         * themselves, not on a marketing page.
         */
        'and marriage as soon as we are both sure' => [
            'reason' => 'A profile option. Soul mate leads, marriage is the destination, and '
                .'the three options now differ only in pace rather than in intention.',
            'surfaces' => ['profile-options'],
        ],

        'and marriage without rushing it' => [
            'reason' => 'The same option at a slower pace. Stored key `marriage_eventually` '
                .'is untouched — it is in MatchingFieldsHash and renaming it would re-match '
                .'the platform for a copy change.',
            'surfaces' => ['profile-options'],
        ],

        'and marriage once we have taken our time' => [
            'reason' => 'The third pace. Replaces "A lasting relationship, leading to '
                .'marriage", which led on the relationship rather than on soul mate.',
            'surfaces' => ['profile-options'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    /**
     * ⚠️ NOTHING LEADS WITH IT.
     *
     * The leading matter is what a person meets first and what a search engine
     * quotes: the title, the meta description, the h1, and the first sentence
     * of the page body. No exceptions here at all — not even an allow-listed
     * one — because "marriage appears early on this page" is the exact thing
     * the client objected to.
     */
    public function test_no_public_page_leads_with_marriage(): void
    {
        $offences = [];

        foreach (PublicSurfaces::paths() as $path) {
            $html = $this->get($path)->getContent();

            foreach ($this->leadingMatter($html) as $where => $text) {
                if ($text === '') {
                    continue;
                }

                $found = Scanners::marriageForwardTerms($text);

                if ($found !== []) {
                    $offences[] = sprintf(
                        '%s %s leads with [%s]: "%s"',
                        $path, $where, implode(', ', $found), $text
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $offences,
            'Marriage-forward language is in the leading matter of a public page. The client '
                .'asked for it demoted, not removed — move it later in the page and, if the '
                .'sentence is a legitimate destination clause, add it to '
                ."MarriageLeadTest::ALLOWED:\n  ".implode("\n  ", $offences)
        );
    }

    /**
     * ⚠️ EVERY OCCURRENCE ANYWHERE IS ACCOUNTED FOR, and every entry on the
     * list is still real.
     */
    public function test_every_occurrence_is_on_the_list_and_every_entry_is_current(): void
    {
        $unexpected = [];
        $seen = array_fill_keys(array_keys(self::ALLOWED), false);

        foreach ($this->everySurface() as $surface => $text) {
            foreach (Scanners::sentences($text) as $sentence) {
                if (Scanners::marriageForwardTerms($sentence) === []) {
                    continue;
                }

                $entry = $this->entryCovering($sentence);

                if ($entry === null) {
                    $unexpected[] = "{$surface}: \"{$sentence}\"";

                    continue;
                }

                $seen[$entry] = true;

                if (! in_array($surface, self::ALLOWED[$entry]['surfaces'], true)) {
                    $unexpected[] = "{$surface}: an approved sentence appeared on a surface it "
                        ."is not listed for — \"{$sentence}\"";
                }
            }
        }

        $this->assertSame(
            [],
            $unexpected,
            'Marriage-forward language appeared somewhere it is not accounted for. Either '
                .'rewrite it in the soul mate register, or add it to MarriageLeadTest::ALLOWED '
                ."with a reason:\n  ".implode("\n  ", $unexpected)
        );

        $stale = array_keys(array_filter($seen, fn (bool $found) => ! $found));

        $this->assertSame(
            [],
            $stale,
            'An entry in MarriageLeadTest::ALLOWED no longer matches anything. A list that '
                .'outlives its subject permits nothing while still passing, which is '
                .'indistinguishable from a working guard. Delete the entry or fix the '
                ."copy:\n  ".implode("\n  ", $stale)
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5), BOTH DIRECTIONS.
     *
     * The planted string is the headline that was actually live. The clean
     * sample is the client's own approved sentence — a reader that flagged it
     * would fail the build on copy he wrote himself, and the person debugging
     * that at 2am would reasonably conclude the guard is broken and remove it.
     */
    public function test_the_reader_catches_a_lead_and_leaves_the_destination_clause_alone(): void
    {
        $planted = 'Find someone ready for marriage.';
        $clean = 'RealVows is for people aged 18 and over, who are seeking to find their true '
            .'soul mate.';

        $this->assertSame(
            ['marriage'],
            Scanners::marriageForwardTerms($planted),
            'The reader cannot see the headline that was actually live.'
        );

        $this->assertSame(
            [],
            Scanners::marriageForwardTerms($clean),
            'The reader flags copy in the new register, which would make it noise.'
        );

        // And the allow-list matcher recognises the destination clause rather
        // than treating every occurrence as an offence.
        $this->assertNotNull(
            $this->entryCovering('Their synergy will help them develop that bond and a lasting '
                .'relationship, leading to marriage. Creating an account and building a full '
                .'profile is free.'),
            'The allow-list does not recognise the sentence it was written for.'
        );
    }

    /**
     * ⚠️ THE READER MUST NOT MATCH INSIDE A LONGER WORD, or the guard becomes
     * noise the first time somebody writes about a merry or a marred anything.
     */
    /**
     * ⚠️ THE READER MUST SEE THE WHOLE REGISTER, NOT ONE WORD OF IT.
     *
     * It originally read for "marriage" and its inflections only. That is the
     * false-pass shape: clean report, thing walks past. The client objected to
     * a register, and "wife", "wedding" and "settle down" land on the same
     * instinct as "marriage" does.
     *
     * "Settle down" is in here specifically because the previous pass
     * INTRODUCED it, as the replacement for "hoping to marry", and nothing
     * noticed for a week.
     */
    public function test_the_reader_covers_the_whole_register_not_just_one_word(): void
    {
        $mustCatch = [
            'settle down' => 'You are both hoping to settle down within the year.',
            'wife' => 'Find a wife who shares your values.',
            'husband' => 'The husband you have been waiting for.',
            'wedding' => 'Start planning the wedding.',
            'spouse' => 'Your future spouse is on RealVows.',
            'bride' => 'Meet your bride.',
            'matrimony' => 'A platform built for matrimony.',
            'tie the knot' => 'People here are ready to tie the knot.',
        ];

        foreach ($mustCatch as $term => $sentence) {
            $this->assertNotSame(
                [],
                Scanners::marriageForwardTerms($sentence),
                "The reader does not see [{$term}]. It carries the same weight as the word the "
                    .'client objected to, and a guard that catches one spelling reports clean '
                    .'while the register walks past it.'
            );
        }
    }

    public function test_the_reader_matches_whole_words_only(): void
    {
        foreach (['merrymaking', 'marred', 'summary', 'marginal', 'groomed', 'unsettled'] as $innocent) {
            $this->assertSame(
                [],
                Scanners::marriageForwardTerms("A sentence containing {$innocent} and nothing else."),
                "The reader flagged [{$innocent}], which is not the word."
            );
        }
    }

    /**
     * Every surface, rendered.
     *
     * @return array<string, string>
     */
    private function everySurface(): array
    {
        $surfaces = [];

        foreach (PublicSurfaces::paths() as $path) {
            $surfaces[$path] = Scanners::visibleText($this->get($path)->getContent());
        }

        $surfaces += PublicSurfaces::whatsAppBodies();

        /*
         * ⚠️ THE PRODUCT'S OWN EMISSION POINTS, not the Vue files that display
         * them. A member reads these strings; no template contains them.
         */
        $surfaces['profile-options'] = implode(' ', [
            implode('. ', ProfileOptions::RELATIONSHIP_GOAL),
            implode('. ', ProfileOptions::MARRIAGE_TIMELINE),
        ]);

        $surfaces['rationale-fragments'] = implode(' ', RationaleWriter::everyFragment());

        $surfaces['copy-deck'] = implode(' ', array_map(
            fn ($v) => is_array($v) ? implode(' ', array_filter($v, 'is_string')) : (string) $v,
            iterator_to_array($this->flatten(trans('realvows'))),
        ));

        return $surfaces;
    }

    /** @return \Generator<string> */
    private function flatten(array $values): \Generator
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                yield from $this->flatten($value);
            } elseif (is_string($value)) {
                yield $value;
            }
        }
    }

    private function entryCovering(string $sentence): ?string
    {
        foreach (self::ALLOWED as $fragment => $_) {
            if (str_contains($sentence, $fragment)) {
                return $fragment;
            }
        }

        return null;
    }

    /**
     * Title, meta description, h1 and the first sentence of the body.
     *
     * @return array<string, string>
     */
    private function leadingMatter(string $html): array
    {
        preg_match('#<title[^>]*>(.*?)</title>#is', $html, $title);
        preg_match('#<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']#is', $html, $description);
        preg_match('#<h1[^>]*>(.*?)</h1>#is', $html, $h1);
        preg_match('#<main\b[^>]*>(.*)</main>#is', $html, $main);

        $body = Scanners::sentences(Scanners::visibleText($main[1] ?? ''));

        return [
            'title' => Scanners::visibleText($title[1] ?? ''),
            'meta description' => html_entity_decode($description[1] ?? '', ENT_QUOTES | ENT_HTML5),
            'h1' => Scanners::visibleText($h1[1] ?? ''),
            'first sentence' => $body[0] ?? '',
        ];
    }
}
