<?php

namespace Tests\Feature;

use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\ProfilePreference;
use App\Models\Verification;
use App\Services\Matching\CompatibilityScorer;
use App\Services\Matching\Copy\CommonalityFinder;
use App\Services\Matching\Copy\RationaleWriter;
use App\Services\Matching\MatchGenerator;
use App\Services\Settings\SettingsService;
use App\Support\GuardianPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\AssertionFailedError;
use Tests\Support\Scanners;
use Tests\TestCase;

/**
 * ⚠️ THE HIGHEST-STAKES RULE IN THE PROJECT.
 *
 * The compatibility mechanism weights something a large part of the client's
 * market would reject outright. No member may ever learn it exists.
 *
 * The CI greps are the cheap second net. THESE TESTS ARE THE GUARANTEE,
 * because they exercise the actual generators rather than reading the source
 * — a template assembled at runtime from two safe halves would pass a grep
 * and fail here, which is the direction that matters.
 */
class ForbiddenLanguageTest extends TestCase
{
    use RefreshDatabase;

    /** Tier 1: banned outright, in any context, anywhere a member can reach. */
    public const TIER_1 = [
        'zodiac', 'star sign', 'sun sign', 'astrolog', 'synastry', 'horoscope',
        'natal', 'birth chart', 'celestial',
        'aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo',
        'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces',
        '♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐', '♑', '♒', '♓',
    ];

    /**
     * Tier 2: banned inside GENERATED COPY only.
     *
     * These words are perfectly innocent in ordinary romantic writing — "down
     * to earth", "a warm fire" — so banning them platform-wide would fire on
     * good copy. Inside a rationale or a commonality they are a tell.
     */
    public const TIER_2 = [
        'fire sign', 'air sign', 'earth sign', 'water sign',
        'fire and air', 'earth and water', 'cardinal', 'mutable',
        'element', 'elemental', 'modality',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        /*
         * ⚠️ AFTER THE SEEDER, NOT BEFORE IT.
         *
         * The seeded default became `required_female` when the client reduced
         * the setting to two choices, and these tests build a woman with
         * introductions — so she is held at the pool gate and every assertion
         * about her matches finds an empty list. That is the gate working.
         *
         * I first set this BEFORE the seeder ran, which overwrote it and
         * changed nothing. A setup line that is silently undone is worse than
         * no line: the test still fails and the reason now looks wrong.
         *
         * `off` is not selectable in the admin any more but is still a valid
         * level in code — it is the documented fail-open.
         */
        app(SettingsService::class)
            ->set('guardian', GuardianPolicy::KEY, GuardianPolicy::OFF);

    }

    /**
     * ⚠️ WHOLE WORDS. A SUBSTRING CHECK FAILED ON THE WORD "CALIBRATION".
     *
     * This used assertStringNotContainsStringIgnoringCase, which matched `libra`
     * inside "Calibration Technician" — an occupation Faker produces at random.
     * So the build went red on a profile containing nothing wrong, and only on
     * the runs where the dice landed that way.
     *
     * ⚠️ AN OVER-EAGER GUARD IS NOT A STRICTER GUARD. It is one that gets
     * overridden, and a guard that has been overridden once is not a guard. The
     * same reasoning is already written into Scanners::contractions, which
     * refuses to flag a possessive apostrophe for exactly this reason.
     *
     * ⚠️ THE BOUNDARY IS ON THE FRONT ONLY, AND THE CONTROL IN THIS FILE IS
     * WHAT FORCED THAT. A boundary at both ends broke every PREFIX in the list:
     * `astrolog` is there precisely so it catches astrology, astrological and
     * astrologer, and `\bastrolog\b` matches none of them.
     *
     * Leading boundary keeps both properties. "Calibration" is not flagged,
     * because the `libra` inside it has a word character in front of it.
     * "ASTROLOGY" still is.
     */

    /**
     * ⚠️ A GLYPH HAS NO WORD BOUNDARY, so it cannot be looked for the same way
     * as a word. `\b` needs a word character on one side, and ♌ is not one, so
     * a boundary pattern silently never matches a sign glyph — which the
     * control in this file caught within a minute of the boundaries going in.
     *
     * Words get a leading boundary. Everything else is a plain substring.
     */
    private static function appears(string $term, string $text): bool
    {
        if (preg_match('/^\w/u', $term) !== 1) {
            return mb_strpos($text, $term) !== false;
        }

        return preg_match('/\b'.preg_quote($term, '/').'/iu', $text) === 1;
    }

    private function assertClean(string $text, string $where): void
    {
        foreach (self::TIER_1 as $term) {
            $this->assertFalse(
                self::appears($term, $text),
                "Tier 1 term [{$term}] appeared in {$where}: \"{$text}\""
            );
        }

        foreach (self::TIER_2 as $term) {
            $this->assertFalse(
                self::appears($term, $text),
                "Tier 2 term [{$term}] appeared in generated copy in {$where}: \"{$text}\""
            );
        }

        /*
         * ⚠️ TIER 3 — PERMITTED IN MARKETING, BANNED HERE. The list and the
         * full reasoning live in Scanners::MECHANISM_ADJACENT so there is one
         * copy of it and one place to argue with.
         */
        $this->assertSame(
            [],
            Scanners::mechanismAdjacentTerms($text),
            "Mechanism-adjacent vocabulary appeared in {$where}. It is allowed on the marketing "
                .'pages — the client wrote it himself — but inside generated match copy it reads '
                .'as the name of a mechanism, and it is one letter-cluster from the term for the '
                ."thing we are concealing: \"{$text}\""
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL FOR assertClean(). Read before trusting this file.
     *
     * Every check below is a negative assertion, and a negative assertion
     * whose search method cannot find the thing is invisible and permanent.
     * It happened on this project once already: json_encode escaping slashes
     * made a guard pass against nothing.
     *
     * This feeds assertClean() strings that MUST trip it, one per tier and
     * one per encoding, and asserts it actually does. If this fails, every
     * green test below it means nothing.
     */
    public function test_the_cleanliness_check_actually_catches_forbidden_terms(): void
    {
        $mustTrip = [
            'a tier 1 word' => 'Your zodiac compatibility is high',
            'a sign name' => 'Two Scorpio matches for you',
            'a sign name mid-sentence' => 'She is a Leo and you are not',
            'different case' => 'ASTROLOGY drives our matching',
            'a glyph' => 'Your sign ♌ suggests warmth',
            'a tier 2 phrase' => 'A fire sign paired with an air sign',
            'modality language' => 'Both cardinal, which explains the friction',
            'mechanism-adjacent vocabulary' => 'Your synergy is unusually strong',
            'the same word capitalised' => 'Synergy between you two is rare',
        ];

        foreach ($mustTrip as $why => $text) {
            $caught = false;

            try {
                $this->assertClean($text, 'the positive control');
            } catch (AssertionFailedError) {
                $caught = true;
            }

            $this->assertTrue(
                $caught,
                "assertClean() did NOT catch {$why}: \"{$text}\". Every other assertion in this file is worthless."
            );
        }
    }

    /**
     * The counterpart: ordinary romantic copy must NOT trip it, or the guard
     * gets switched off the first time it blocks good writing.
     *
     * "Down to earth" is exactly the phrase that made the old flat grep
     * unusable.
     */
    public function test_ordinary_romantic_copy_does_not_trip_the_check(): void
    {
        $mustPass = [
            'A patient, home-building match with closely aligned family values.',
            'You both value independence and a similar pace in a relationship.',
            'She is down to earth and he is warm company.',
            'Shared warmth and directness, with aligned expectations on commitment.',
            'You both speak Urdu and English.',
            'Neither of you smokes.',
        ];

        foreach ($mustPass as $text) {
            $this->assertClean($text, 'ordinary copy');
        }
    }

    /**
     * ⚠️ THE CLIENT'S OWN SENTENCE MUST SURVIVE THE ZODIAC READER.
     *
     * "Their synergy will help them develop that bond" is his wording for the
     * legal pages. The banned term is `synastry`. The two are one letter-cluster
     * apart, and the obvious "tighten the reader" move — matching a `syn`
     * prefix — would fail the build on the client's own approved copy and look,
     * from the failure message, like a genuine zodiac leak.
     *
     * This asserts the distinction in both directions so nobody has to
     * rediscover it under time pressure.
     */
    public function test_the_clients_wording_is_not_mistaken_for_the_banned_term(): void
    {
        $his = 'RealVows is for people aged 18 and over, who are seeking to find their true soul '
            .'mate. Their synergy will help them develop that bond and a lasting relationship, '
            .'leading to marriage.';

        $this->assertSame(
            [],
            Scanners::forbiddenTerms($his),
            'The zodiac reader flagged the client\'s own approved sentence. Check it has not been '
                .'widened to a prefix: "synergy" is his word, "synastry" is the banned one.'
        );

        // And the narrower reader, which is applied only to generated copy,
        // does see it — otherwise the ban in assertClean() is decorative.
        $this->assertSame(
            ['synergy'],
            Scanners::mechanismAdjacentTerms($his),
            'The mechanism-adjacent reader cannot see the word it exists for.'
        );
    }

    /**
     * Every fragment the writer can possibly emit, checked directly. This is
     * exhaustive over the template library rather than a sample of it.
     */
    public function test_every_rationale_fragment_is_clean(): void
    {
        $fragments = RationaleWriter::everyFragment();

        $this->assertGreaterThanOrEqual(
            40,
            count($fragments),
            'The brief calls for at least forty fragments so members do not read the same line twice.'
        );

        foreach ($fragments as $fragment) {
            $this->assertClean($fragment, 'the rationale template library');
        }
    }

    /**
     * A thousand generated matches across the full range of profile data.
     * Catches anything assembled at runtime that no single template contains.
     */
    public function test_a_thousand_generated_rationales_are_clean(): void
    {
        $writer = app(RationaleWriter::class);
        $finder = app(CommonalityFinder::class);
        $scorer = app(CompatibilityScorer::class);

        $seekers = Profile::factory()->count(20)->woman()->create();
        $candidates = Profile::factory()->count(20)->man()->create();

        foreach ($seekers as $s) {
            ProfilePreference::factory()->create(['profile_id' => $s->id]);
        }
        foreach ($candidates as $c) {
            ProfilePreference::factory()->create(['profile_id' => $c->id]);
        }

        $checked = 0;

        foreach ($seekers as $seeker) {
            foreach ($candidates as $candidate) {
                $seeker = $seeker->fresh();
                $candidate = $candidate->fresh();

                $result = $scorer->score($seeker, $candidate);

                $this->assertClean($writer->write($seeker, $candidate, $result), 'a generated rationale');

                foreach ($finder->find($seeker, $candidate) as $line) {
                    $this->assertClean($line, 'a generated commonality');
                }

                $checked++;
            }
        }

        $this->assertGreaterThanOrEqual(400, $checked);
    }

    /**
     * ⚠️ THE STRUCTURAL GUARANTEE, INVERTED.
     *
     * This used to prove the writer could not key a sentence on the affinity
     * component, by handing it an overwhelming affinity score and asserting the
     * copy did not change. The component is deleted, so that test now proves
     * nothing: it would pass against a writer that had never heard of the key.
     *
     * ⚠️ A TEST THAT PASSES BECAUSE ITS SUBJECT NO LONGER EXISTS IS THE
     * QUIETEST KIND OF DEAD TEST, and this file is where that matters most.
     *
     * What is still worth guaranteeing is that the writer only ever speaks
     * about components it has templates for. An unknown key must not produce a
     * sentence, because the next time somebody adds a component it will arrive
     * here before anybody has decided what may be said about it.
     */
    public function test_the_writer_says_nothing_about_a_component_it_has_no_words_for(): void
    {
        $writer = app(RationaleWriter::class);

        $seeker = Profile::factory()->woman()->create();
        $candidate = Profile::factory()->man()->create();

        $withUnknownDominant = $writer->write($seeker, $candidate, [
            'components' => [
                'some_future_component' => ['raw' => 100.0, 'weight' => 40, 'weighted' => 40.0],
                'values' => ['raw' => 71.0, 'weight' => 25, 'weighted' => 17.75],
            ],
        ]);

        // It may open differently, because the opener bands on the top score.
        // What it must never do is describe the component it has no words for.
        $this->assertStringNotContainsString('some_future_component', $withUnknownDominant);
        $this->assertClean($withUnknownDominant, 'a rationale with an unknown component');

        $this->assertNotSame(
            '',
            trim($withUnknownDominant),
            'The writer fell silent entirely rather than falling back to the components it knows.'
        );
    }

    /**
     * ⚠️ Commonalities are assembled only from fields members typed. A line
     * derived from a date of birth could be worked backwards to the
     * mechanism, so the finder must never read one.
     */
    public function test_commonalities_never_derive_from_a_date_of_birth(): void
    {
        $finder = app(CommonalityFinder::class);

        $a = Profile::factory()->woman()->create(['date_of_birth' => '1990-04-01']);
        $b = Profile::factory()->man()->create(['date_of_birth' => '1990-04-02']);

        $sameBirthday = $finder->find($a->fresh(), $b->fresh());

        // Change only the dates of birth. Nothing about the output may move.
        // ⚠️ The derived columns used to be set here alongside the dates,
        // because writing a date of birth used to write them. Both columns are
        // dropped. The dates alone are now the whole of what changes, which is
        // exactly what this test claims to be varying.
        $a->forceFill(['date_of_birth' => '1975-11-30'])->save();
        $b->forceFill(['date_of_birth' => '1966-01-15'])->save();

        $this->assertSame(
            $sameBirthday,
            $finder->find($a->fresh(), $b->fresh()),
            'Commonalities moved when only the dates of birth changed, so one of them reads that field.'
        );
    }

    /**
     * The end-to-end version: real generated matches, checked as they are
     * stored, including the member-facing columns.
     */
    public function test_stored_match_copy_is_clean(): void
    {
        $seeker = Profile::factory()->woman()->create();
        Verification::factory()->verified()->create(['profile_id' => $seeker->id]);
        ProfilePreference::factory()->create(['profile_id' => $seeker->id]);

        for ($i = 0; $i < 10; $i++) {
            $c = Profile::factory()->man()->create(['date_of_birth' => '199'.$i.'-0'.($i % 9 + 1).'-15']);
            Verification::factory()->verified()->create(['profile_id' => $c->id]);
            ProfilePreference::factory()->create(['profile_id' => $c->id]);
        }

        app(MatchGenerator::class)->generateFor($seeker->fresh());

        $matches = MatchRecord::where('seeker_profile_id', $seeker->id)->get();
        $this->assertGreaterThan(0, $matches->count(), 'No matches were generated, so nothing was actually checked.');

        foreach ($matches as $match) {
            $this->assertClean((string) $match->compatibility_rationale, "match #{$match->id} rationale");

            foreach ($match->commonalities ?? [] as $line) {
                $this->assertClean($line, "match #{$match->id} commonalities");
            }
        }
    }

    /**
     * ⚠️ The breakdown is the only place the mechanism is legible, and it is
     * for staff. It must never be serialised to a member — asserted here
     * against the model's own JSON, which is what any careless
     * `->toArray()` would produce.
     */
    public function test_the_score_breakdown_is_hidden_from_model_serialisation(): void
    {
        $seeker = Profile::factory()->woman()->create();
        $candidate = Profile::factory()->man()->create();

        $match = MatchRecord::create([
            'seeker_profile_id' => $seeker->id,
            'matched_profile_id' => $candidate->id,
            'total_score' => 88,
            'score_breakdown' => ['components' => ['values' => ['raw' => 92]]],
            'status' => MatchRecord::STATUS_TEASER,
        ]);

        $this->assertArrayNotHasKey('score_breakdown', $match->toArray());
        $this->assertStringNotContainsString('affinity', $match->toJson());

        // Staff still get it when they ask for it explicitly.
        $this->assertNotNull($match->fresh()->score_breakdown);
    }

    /**
     * ⚠️ THE COLUMNS ARE GONE, NOT HIDDEN.
     *
     * This asserted that `sign` and `element` were computed and then hidden
     * from serialisation. Both columns are dropped and the calculator is
     * deleted, so hiding is no longer the mechanism: absence is.
     *
     * Kept rather than deleted, because the thing worth proving is unchanged —
     * a serialised Profile must carry no trace of the removed mechanism — and
     * because a future migration re-adding either column should fail here.
     */
    public function test_the_removed_columns_are_absent_from_a_serialised_profile(): void
    {
        $profile = Profile::factory()->create(['date_of_birth' => '1990-04-01']);

        $this->assertArrayNotHasKey('sign', $profile->toArray());
        $this->assertArrayNotHasKey('element', $profile->toArray());

        $this->assertFalse(
            Schema::hasColumn('profiles', 'sign'),
            'The profiles.sign column is back. The component that needed it was removed deliberately.'
        );

        $this->assertClean($profile->toJson(), 'a serialised profile');
    }
}
