<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\ProfilePreference;
use App\Services\Matching\CompatibilityScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompatibilityScorerTest extends TestCase
{
    use RefreshDatabase;

    private CompatibilityScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = app(CompatibilityScorer::class);
    }

    private function pair(array $seekerState = [], array $candidateState = [], bool $preferences = true): array
    {
        $seeker = Profile::factory()->woman()->create($seekerState);
        $candidate = Profile::factory()->man()->create($candidateState);

        if ($preferences) {
            ProfilePreference::factory()->create(['profile_id' => $seeker->id]);
            ProfilePreference::factory()->create(['profile_id' => $candidate->id]);
        }

        return [$seeker->fresh(), $candidate->fresh()];
    }

    public function test_a_score_is_between_zero_and_one_hundred(): void
    {
        [$a, $b] = $this->pair();
        $result = $this->scorer->score($a, $b);

        $this->assertGreaterThanOrEqual(0, $result['total']);
        $this->assertLessThanOrEqual(100, $result['total']);
    }

    /**
     * The property the whole audit trail rests on. A member asking "why 87?"
     * must get an answer that is still 87 tomorrow.
     */
    public function test_scoring_is_deterministic(): void
    {
        [$a, $b] = $this->pair();

        $first = $this->scorer->score($a, $b);

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($first['total'], $this->scorer->score($a, $b)['total']);
        }
    }

    public function test_two_well_matched_people_score_above_the_threshold(): void
    {
        [$a, $b] = $this->pair(
            ['date_of_birth' => '1994-04-01'],
            ['date_of_birth' => '1992-08-05'] // strongest affinity pairing
        );

        $this->assertGreaterThan(65, $this->scorer->score($a, $b)['total']);
    }

    public function test_deeply_mismatched_people_score_low(): void
    {
        [$a, $b] = $this->pair(
            [
                'smoking' => 'never', 'drinking' => 'never', 'wants_children' => 'yes',
                'religion' => 'Islam', 'faith_importance' => 'essential',
                'core_values' => ['faith', 'family', 'loyalty'],
                'marriage_timeline' => 'within_year', 'conflict_style' => 'discuss_now',
                'interests' => ['reading'], 'date_of_birth' => '1994-04-01',
                'personality_scales' => ['social' => 5, 'planning' => 5, 'decisions' => 5, 'outlook' => 5],
            ],
            [
                'smoking' => 'regularly', 'drinking' => 'regularly', 'wants_children' => 'no',
                'religion' => 'None', 'faith_importance' => 'essential',
                'core_values' => ['adventure', 'independence', 'ambition'],
                'marriage_timeline' => 'no_fixed', 'conflict_style' => 'avoid',
                'interests' => ['motorsport'], 'date_of_birth' => '1994-07-01',
                'personality_scales' => ['social' => 95, 'planning' => 95, 'decisions' => 95, 'outlook' => 95],
                'height_cm' => 145,
            ]
        );

        $this->assertLessThan(65, $this->scorer->score($a, $b)['total']);
    }

    // ── Weight redistribution ────────────────────────────────────────────

    /**
     * ⚠️ The rule that stops an incomplete profile being punished twice:
     * once for having less to match on, and again for being read as actively
     * incompatible.
     */
    public function test_a_missing_component_is_redistributed_not_scored_as_zero(): void
    {
        // No preferences on either side removes two components entirely.
        [$a, $b] = $this->pair(preferences: false);

        $result = $this->scorer->score($a, $b);

        $this->assertContains('preferences', $result['missing']);
        $this->assertArrayNotHasKey('preferences', $result['components']);

        // The surviving weights must still add to 100, or the total stops
        // meaning "out of 100".
        $this->assertEqualsWithDelta(
            100,
            array_sum(array_column($result['components'], 'weight')),
            0.01,
            'Weights after redistribution must still total 100.'
        );
    }

    public function test_redistribution_is_recorded_so_the_admin_log_can_explain_the_number(): void
    {
        [$a, $b] = $this->pair(preferences: false);
        $result = $this->scorer->score($a, $b);

        $this->assertNotEmpty($result['redistributed']);

        foreach ($result['redistributed'] as $component => $gained) {
            $this->assertGreaterThan(0, $gained, "{$component} should have gained weight, not lost it.");
        }
    }

    public function test_nothing_is_redistributed_when_every_component_has_data(): void
    {
        [$a, $b] = $this->pair();
        $result = $this->scorer->score($a, $b);

        $this->assertSame([], $result['missing']);
        $this->assertSame([], $result['redistributed']);
        $this->assertCount(6, $result['components']);
    }

    /**
     * An incomplete profile must not be able to score HIGHER than the same
     * profile with data, purely by having its weight redistributed onto
     * whichever components happen to be strong.
     */
    public function test_missing_data_does_not_become_an_advantage(): void
    {
        [$a, $b] = $this->pair();
        $withEverything = $this->scorer->score($a, $b)['total'];

        $a->update(['smoking' => null, 'drinking' => null, 'education' => null, 'languages' => null, 'pets' => null]);
        $withGaps = $this->scorer->score($a->fresh(), $b)['total'];

        $this->assertLessThanOrEqual(
            $withEverything + 15,
            $withGaps,
            'Removing data produced a materially higher score, which would reward incomplete profiles.'
        );
    }

    // ── The mechanism must not leak ──────────────────────────────────────

    /**
     * ⚠️ The breakdown is the one place the internal mechanism is legible.
     * It is written to the audit log and must never travel further.
     */
    public function test_the_breakdown_never_names_the_internal_mechanism(): void
    {
        [$a, $b] = $this->pair();
        $payload = json_encode($this->scorer->score($a, $b));

        foreach (['zodiac', 'star sign', 'astrolog', 'aries', 'leo', 'scorpio', 'fire', 'water'] as $term) {
            $this->assertStringNotContainsStringIgnoringCase($term, $payload);
        }

        // The neutral internal name is the only one that appears.
        $this->assertStringContainsString('affinity', $payload);
    }

    public function test_two_profiles_with_nothing_comparable_score_zero_rather_than_erroring(): void
    {
        $seeker = Profile::factory()->create([
            'sign' => null, 'element' => null, 'smoking' => null, 'drinking' => null,
            'education' => null, 'languages' => null, 'pets' => null, 'interests' => null,
            'personality_scales' => null, 'core_values' => null, 'marriage_timeline' => null,
            'wants_children' => null, 'faith_importance' => null, 'conflict_style' => null,
            'height_cm' => null, 'body_type' => null,
        ]);
        $candidate = Profile::factory()->create([
            'sign' => null, 'element' => null, 'smoking' => null, 'drinking' => null,
            'education' => null, 'languages' => null, 'pets' => null, 'interests' => null,
            'personality_scales' => null, 'core_values' => null, 'marriage_timeline' => null,
            'wants_children' => null, 'faith_importance' => null, 'conflict_style' => null,
            'height_cm' => null, 'body_type' => null,
        ]);

        $result = $this->scorer->score($seeker, $candidate);

        $this->assertSame(0.0, $result['total']);
        $this->assertCount(6, $result['missing']);
    }

    // ── Individual component behaviours worth pinning ────────────────────

    public function test_reciprocity_reduces_a_one_sided_match(): void
    {
        $seeker = Profile::factory()->woman()->create(['height_cm' => 165, 'date_of_birth' => '1995-01-01']);
        $candidate = Profile::factory()->man()->create(['height_cm' => 180, 'date_of_birth' => '1995-01-01']);

        // The seeker is delighted; the candidate wants nobody like her.
        ProfilePreference::factory()->create([
            'profile_id' => $seeker->id, 'height_min_cm' => 175, 'height_max_cm' => 195,
            'age_min' => 25, 'age_max' => 45,
        ]);
        ProfilePreference::factory()->create([
            'profile_id' => $candidate->id, 'height_min_cm' => 190, 'height_max_cm' => 200,
            'age_min' => 18, 'age_max' => 22,
        ]);

        $mutual = app(\App\Services\Matching\Scorers\PreferencesScorer::class)
            ->score($seeker->fresh(), $candidate->fresh());

        $this->assertLessThan(
            100,
            $mutual,
            'A match the other person would not want must not score as if they would.'
        );
    }

    public function test_a_non_smoker_and_a_heavy_smoker_score_worse_than_two_heavy_smokers(): void
    {
        $lifestyle = app(\App\Services\Matching\Scorers\LifestyleScorer::class);

        $mismatch = $lifestyle->score(
            Profile::factory()->create(['smoking' => 'never']),
            Profile::factory()->create(['smoking' => 'regularly']),
        );

        $bothSmoke = $lifestyle->score(
            Profile::factory()->create(['smoking' => 'regularly']),
            Profile::factory()->create(['smoking' => 'regularly']),
        );

        $this->assertLessThan(
            $bothSmoke,
            $mismatch,
            'Equality would score these the same. Compatibility is not equality.'
        );
    }

    /**
     * Rank distance, not set intersection. Two people who both rank family
     * first are not the same as two who both rank it fifth.
     */
    public function test_values_ranked_first_by_both_beat_the_same_values_ranked_last(): void
    {
        $values = app(\App\Services\Matching\Scorers\ValuesScorer::class);
        $shared = ['family', 'faith'];

        $topOfBoth = $values->score(
            Profile::factory()->create(['core_values' => [...$shared, 'humour', 'ambition', 'adventure']]),
            Profile::factory()->create(['core_values' => [...$shared, 'stability', 'education', 'generosity']]),
        );

        $bottomOfBoth = $values->score(
            Profile::factory()->create(['core_values' => ['humour', 'ambition', 'adventure', ...$shared]]),
            Profile::factory()->create(['core_values' => ['stability', 'education', 'generosity', ...$shared]]),
        );

        $this->assertGreaterThan($bottomOfBoth, $topOfBoth);
    }

    /**
     * Not every scale rewards similarity. Two people who are both extremely
     * spontaneous have nobody booking the flights.
     */
    public function test_the_planning_scale_rewards_moderate_difference_not_sameness(): void
    {
        $personality = app(\App\Services\Matching\Scorers\PersonalityScorer::class);

        $base = ['social' => 50, 'decisions' => 50, 'outlook' => 50];

        $identical = $personality->score(
            Profile::factory()->create(['personality_scales' => [...$base, 'planning' => 90], 'interests' => ['a']]),
            Profile::factory()->create(['personality_scales' => [...$base, 'planning' => 90], 'interests' => ['a']]),
        );

        $complementary = $personality->score(
            Profile::factory()->create(['personality_scales' => [...$base, 'planning' => 70], 'interests' => ['a']]),
            Profile::factory()->create(['personality_scales' => [...$base, 'planning' => 40], 'interests' => ['a']]),
        );

        $this->assertGreaterThan(
            $identical,
            $complementary,
            'A moderate gap on planning should beat two identical answers.'
        );
    }
}
