<?php

namespace Tests\Feature;

use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\ProfilePreference;
use App\Models\Verification;
use App\Services\Matching\Copy\CommonalityFinder;
use App\Services\Matching\Copy\RationaleWriter;
use App\Services\Matching\MatchGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function assertClean(string $text, string $where): void
    {
        foreach (self::TIER_1 as $term) {
            $this->assertStringNotContainsStringIgnoringCase(
                $term, $text, "Tier 1 term [{$term}] appeared in {$where}: \"{$text}\""
            );
        }

        foreach (self::TIER_2 as $term) {
            $this->assertStringNotContainsStringIgnoringCase(
                $term, $text, "Tier 2 term [{$term}] appeared in generated copy in {$where}: \"{$text}\""
            );
        }
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
        ];

        foreach ($mustTrip as $why => $text) {
            $caught = false;

            try {
                $this->assertClean($text, 'the positive control');
            } catch (\PHPUnit\Framework\AssertionFailedError) {
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
        $scorer = app(\App\Services\Matching\CompatibilityScorer::class);

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
     * ⚠️ The structural guarantee, not a copy check.
     *
     * The writer strips the affinity component before choosing anything, so
     * no sentence can be keyed on it. Proven by making affinity overwhelming
     * and confirming the copy does not change to describe it.
     */
    public function test_the_writer_cannot_key_a_sentence_on_the_internal_component(): void
    {
        $writer = app(RationaleWriter::class);

        $seeker = Profile::factory()->woman()->create();
        $candidate = Profile::factory()->man()->create();

        $withAffinityDominant = $writer->write($seeker, $candidate, [
            'components' => [
                'affinity' => ['raw' => 100.0, 'weight' => 25, 'weighted' => 25.0],
                'values' => ['raw' => 71.0, 'weight' => 17, 'weighted' => 12.0],
            ],
        ]);

        $withAffinityAbsent = $writer->write($seeker, $candidate, [
            'components' => [
                'values' => ['raw' => 71.0, 'weight' => 17, 'weighted' => 12.0],
            ],
        ]);

        $this->assertSame(
            $withAffinityAbsent,
            $withAffinityDominant,
            'The rationale changed when the internal component changed, which means it can be read from the copy.'
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
        $a->forceFill(['date_of_birth' => '1975-11-30', 'sign' => 'sagittarius', 'element' => 'fire'])->save();
        $b->forceFill(['date_of_birth' => '1966-01-15', 'sign' => 'capricorn', 'element' => 'earth'])->save();

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
            'score_breakdown' => ['components' => ['affinity' => ['raw' => 92]]],
            'status' => MatchRecord::STATUS_TEASER,
        ]);

        $this->assertArrayNotHasKey('score_breakdown', $match->toArray());
        $this->assertStringNotContainsString('affinity', $match->toJson());

        // Staff still get it when they ask for it explicitly.
        $this->assertNotNull($match->fresh()->score_breakdown);
    }

    /**
     * The internal columns must not survive a careless serialisation of a
     * whole Profile either.
     */
    public function test_the_internal_profile_columns_are_hidden(): void
    {
        $profile = Profile::factory()->create(['date_of_birth' => '1990-04-01']);

        $this->assertNotNull($profile->sign, 'The derivation should still be running.');

        $json = $profile->toJson();

        $this->assertArrayNotHasKey('sign', $profile->toArray());
        $this->assertArrayNotHasKey('element', $profile->toArray());
        $this->assertStringNotContainsStringIgnoringCase('aries', $json);
    }
}
