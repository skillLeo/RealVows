<?php

namespace Tests\Feature;

use App\Jobs\GenerateTeaserMatches;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\ProfilePreference;
use App\Models\User;
use App\Models\Verification;
use App\Services\Matching\CandidatePool;
use App\Services\Matching\MatchGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MatchGenerationTest extends TestCase
{
    use RefreshDatabase;

    private CandidatePool $pool;

    protected function setUp(): void
    {
        parent::setUp();


        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);

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
        app(\App\Services\Settings\SettingsService::class)
            ->set('guardian', \App\Support\GuardianPolicy::KEY, \App\Support\GuardianPolicy::OFF);


        $this->pool = app(CandidatePool::class);
    }

    /** A woman seeking a man, verified, with preferences. */
    private function seeker(array $state = []): Profile
    {
        $profile = Profile::factory()->woman()->create($state);
        Verification::factory()->verified()->create(['profile_id' => $profile->id]);
        ProfilePreference::factory()->create(['profile_id' => $profile->id]);

        return $profile->fresh();
    }

    private function candidate(array $state = [], array $preferences = []): Profile
    {
        $profile = Profile::factory()->man()->create($state);
        Verification::factory()->verified()->create(['profile_id' => $profile->id]);
        ProfilePreference::factory()->create(['profile_id' => $profile->id, ...$preferences]);

        return $profile->fresh();
    }

    private function poolIdsFor(Profile $seeker): array
    {
        return $this->pool->for($seeker)->pluck('id')->all();
    }

    // ── The hard filters, one test each ──────────────────────────────────

    public function test_a_seeker_is_never_their_own_candidate(): void
    {
        $seeker = $this->seeker();

        $this->assertNotContains($seeker->id, $this->poolIdsFor($seeker));
    }

    public function test_gender_and_seeking_must_align_in_both_directions(): void
    {
        $seeker = $this->seeker();

        $wanted = $this->candidate();
        $wrongGender = $this->candidate(['gender' => 'woman', 'seeking' => 'man']);
        $notSeekingHer = $this->candidate(['gender' => 'man', 'seeking' => 'man']);

        $ids = $this->poolIdsFor($seeker);

        $this->assertContains($wanted->id, $ids);
        $this->assertNotContains($wrongGender->id, $ids);
        $this->assertNotContains($notSeekingHer->id, $ids);
    }

    public function test_a_seeker_who_stated_nothing_matches_nobody_rather_than_everybody(): void
    {
        $seeker = $this->seeker(['gender' => null, 'seeking' => null]);
        $this->candidate();

        $this->assertSame(
            [],
            $this->poolIdsFor($seeker),
            'An unanswered question is not consent to be shown to the whole platform.'
        );
    }

    /**
     * ⚠️ The gate that matters most. An unverified profile can never enter a
     * pool, be scored, or be shown to anybody.
     */
    public function test_an_unverified_profile_never_enters_a_pool(): void
    {
        $seeker = $this->seeker();

        $unverified = Profile::factory()->man()->create();
        ProfilePreference::factory()->create(['profile_id' => $unverified->id]);

        $pending = Profile::factory()->man()->create();
        Verification::factory()->create(['profile_id' => $pending->id, 'status' => Verification::STATUS_PENDING]);

        $rejected = Profile::factory()->man()->create();
        Verification::factory()->create(['profile_id' => $rejected->id, 'status' => Verification::STATUS_REJECTED]);

        $ids = $this->poolIdsFor($seeker);

        $this->assertNotContains($unverified->id, $ids);
        $this->assertNotContains($pending->id, $ids);
        $this->assertNotContains($rejected->id, $ids);
    }

    /**
     * `is_active` is deliberately not mass-assignable, so suspension cannot
     * happen through a stray update() — it has to be forced, on purpose.
     * That is worth preserving, so the test suspends the way the application
     * would have to rather than the way that would be convenient here.
     */
    public function test_a_suspended_account_is_removed(): void
    {
        $seeker = $this->seeker();
        $suspended = $this->candidate();

        $suspended->user->forceFill(['is_active' => false])->save();

        $this->assertNotContains($suspended->id, $this->poolIdsFor($seeker));
    }

    public function test_a_deleted_account_is_removed(): void
    {
        $seeker = $this->seeker();
        $deleted = $this->candidate();

        $deleted->user->delete(); // soft delete

        $this->assertNotContains(
            $deleted->id,
            $this->poolIdsFor($seeker),
            'A soft-deleted account must not remain matchable.'
        );
    }

    public function test_an_unsubmitted_profile_is_removed(): void
    {
        $seeker = $this->seeker();
        $draft = $this->candidate(['submitted_at' => null]);

        $this->assertNotContains($draft->id, $this->poolIdsFor($seeker));
    }

    public function test_under_eighteens_are_removed_even_if_a_row_somehow_exists(): void
    {
        $seeker = $this->seeker();
        $child = $this->candidate(['date_of_birth' => now()->subYears(16)->toDateString()]);

        $this->assertNotContains($child->id, $this->poolIdsFor($seeker));
    }

    public function test_distance_preference_removes_the_far_away_but_keeps_the_willing_to_relocate(): void
    {
        $seeker = $this->seeker(['city' => 'Kingston', 'country' => 'Jamaica']);
        $seeker->preferences->update(['distance_preference' => 'same_city']);

        $local = $this->candidate(['city' => 'Kingston', 'country' => 'Jamaica']);
        $faraway = $this->candidate(['city' => 'Toronto', 'country' => 'Canada', 'willing_to_relocate' => false]);
        $willing = $this->candidate(['city' => 'Toronto', 'country' => 'Canada', 'willing_to_relocate' => true]);

        $ids = $this->poolIdsFor($seeker->fresh());

        $this->assertContains($local->id, $ids);
        $this->assertNotContains($faraway->id, $ids);
        $this->assertContains($willing->id, $ids, 'Willingness to relocate answers the question being asked.');
    }

    public function test_the_children_conflict_removes_the_irreconcilable_but_not_the_undecided(): void
    {
        $seeker = $this->seeker(['wants_children' => 'yes']);

        $alsoWants = $this->candidate(['wants_children' => 'yes']);
        $refuses = $this->candidate(['wants_children' => 'no']);
        $open = $this->candidate(['wants_children' => 'open']);
        $undecided = $this->candidate(['wants_children' => 'undecided']);

        $ids = $this->poolIdsFor($seeker);

        $this->assertContains($alsoWants->id, $ids);
        $this->assertNotContains($refuses->id, $ids);
        $this->assertContains($open->id, $ids, "'Open' is not a position, so it must not eliminate.");
        $this->assertContains($undecided->id, $ids);
    }

    public function test_each_deal_breaker_eliminates_rather_than_deducts(): void
    {
        $seeker = $this->seeker(['religion' => 'Islam', 'city' => 'Kingston']);

        $cases = [
            'smoking' => [['smoking' => 'regularly'], ['smoking' => 'never']],
            'drinking' => [['drinking' => 'regularly'], ['drinking' => 'never']],
            'has_children' => [['has_children' => 'yes_at_home'], ['has_children' => 'none']],
            'no_children_wanted' => [['wants_children' => 'no'], ['wants_children' => 'yes']],
            'different_faith' => [['religion' => 'Christianity'], ['religion' => 'Islam']],
        ];

        foreach ($cases as $breaker => [$violating, $complying]) {
            $seeker->preferences->update(['deal_breakers' => [$breaker]]);

            $bad = $this->candidate($violating);
            $good = $this->candidate($complying);

            $ids = $this->poolIdsFor($seeker->fresh());

            $this->assertNotContains($bad->id, $ids, "Deal-breaker [{$breaker}] did not eliminate.");
            $this->assertContains($good->id, $ids, "Deal-breaker [{$breaker}] eliminated somebody who complies.");
        }
    }

    public function test_body_type_only_eliminates_when_explicitly_marked_a_deal_breaker(): void
    {
        $seeker = $this->seeker();
        $seeker->preferences->update(['body_types' => ['slim', 'athletic']]);

        $offList = $this->candidate(['body_type' => 'plus_size']);

        // Off by default: removing people outright must be a deliberate act.
        $this->assertContains($offList->id, $this->poolIdsFor($seeker->fresh()));

        $seeker->update(['body_type_is_dealbreaker' => true]);

        $this->assertNotContains($offList->id, $this->poolIdsFor($seeker->fresh()));
    }

    public function test_a_prior_pass_removes_the_pair_in_both_directions(): void
    {
        $seeker = $this->seeker();
        $passedByMe = $this->candidate();
        $passedMe = $this->candidate();

        MatchRecord::create([
            'seeker_profile_id' => $seeker->id, 'matched_profile_id' => $passedByMe->id,
            'total_score' => 80, 'score_breakdown' => [], 'status' => MatchRecord::STATUS_UNLOCKED,
            'response' => MatchRecord::RESPONSE_PASSED,
        ]);

        MatchRecord::create([
            'seeker_profile_id' => $passedMe->id, 'matched_profile_id' => $seeker->id,
            'total_score' => 80, 'score_breakdown' => [], 'status' => MatchRecord::STATUS_UNLOCKED,
            'response' => MatchRecord::RESPONSE_PASSED,
        ]);

        $ids = $this->poolIdsFor($seeker);

        $this->assertNotContains($passedByMe->id, $ids);
        $this->assertNotContains($passedMe->id, $ids, 'A pass is final in both directions.');
    }

    public function test_an_existing_pair_is_never_proposed_twice(): void
    {
        $seeker = $this->seeker();
        $already = $this->candidate();

        MatchRecord::create([
            'seeker_profile_id' => $seeker->id, 'matched_profile_id' => $already->id,
            'total_score' => 80, 'score_breakdown' => [], 'status' => MatchRecord::STATUS_TEASER,
        ]);

        $this->assertNotContains($already->id, $this->poolIdsFor($seeker));
    }

    // ── Selection and generation ─────────────────────────────────────────

    public function test_generation_writes_teasers_capped_at_the_batch_size(): void
    {
        $seeker = $this->seeker();

        for ($i = 0; $i < 14; $i++) {
            $this->candidate(['date_of_birth' => '1992-08-0'.($i % 9 + 1)]);
        }

        app(MatchGenerator::class)->generateFor($seeker);

        $matches = MatchRecord::where('seeker_profile_id', $seeker->id)->get();

        $this->assertLessThanOrEqual(8, $matches->count());
        $this->assertTrue($matches->every(fn ($m) => $m->status === MatchRecord::STATUS_TEASER));
        $this->assertTrue($matches->every(fn ($m) => $m->unlocked_at === null));
    }

    public function test_nobody_below_the_threshold_is_ever_written(): void
    {
        $seeker = $this->seeker();
        for ($i = 0; $i < 6; $i++) {
            $this->candidate();
        }

        app(MatchGenerator::class)->generateFor($seeker);

        foreach (MatchRecord::where('seeker_profile_id', $seeker->id)->get() as $match) {
            $this->assertGreaterThanOrEqual(65, (float) $match->total_score);
        }
    }

    public function test_running_generation_twice_does_not_duplicate_anybody(): void
    {
        $seeker = $this->seeker();
        for ($i = 0; $i < 5; $i++) {
            $this->candidate();
        }

        $generator = app(MatchGenerator::class);
        $generator->generateFor($seeker);
        $first = MatchRecord::where('seeker_profile_id', $seeker->id)->pluck('matched_profile_id')->sort()->values();

        $generator->generateFor($seeker->fresh());
        $second = MatchRecord::where('seeker_profile_id', $seeker->id)->pluck('matched_profile_id')->sort()->values();

        $this->assertEquals($first, $second);
    }

    public function test_generation_records_when_it_last_ran(): void
    {
        $seeker = $this->seeker();
        $this->candidate();

        app(MatchGenerator::class)->generateFor($seeker);

        $this->assertNotNull($seeker->fresh()->last_matched_at);
    }

    public function test_an_unverified_seeker_generates_nothing(): void
    {
        $profile = Profile::factory()->woman()->create();
        ProfilePreference::factory()->create(['profile_id' => $profile->id]);
        $this->candidate();

        $this->assertSame(0, app(MatchGenerator::class)->generateFor($profile->fresh()));
    }

    // ── The trigger ──────────────────────────────────────────────────────

    /**
     * ⚠️ Matching runs on APPROVAL, before payment. A member logs in to find
     * people already waiting for them, which is the engine of the product.
     */
    public function test_approving_a_verification_queues_the_generation(): void
    {
        Queue::fake();

        $profile = Profile::factory()->woman()->create();
        $verification = Verification::factory()->create(['profile_id' => $profile->id]);

        $reviewer = User::factory()->create();
        $reviewer->assignRole('super_admin');

        app(\App\Services\Verification\VerificationService::class)->approve($verification, $reviewer);

        Queue::assertPushed(
            GenerateTeaserMatches::class,
            fn ($job) => $job->profileId === $profile->id
        );
    }

    public function test_the_job_does_nothing_for_a_profile_that_lost_its_verification(): void
    {
        $profile = Profile::factory()->woman()->create();
        ProfilePreference::factory()->create(['profile_id' => $profile->id]);
        $this->candidate();

        (new GenerateTeaserMatches($profile->id))->handle(app(MatchGenerator::class));

        $this->assertSame(0, MatchRecord::where('seeker_profile_id', $profile->id)->count());
    }

    public function test_the_job_survives_a_profile_that_no_longer_exists(): void
    {
        (new GenerateTeaserMatches(999999))->handle(app(MatchGenerator::class));

        $this->assertTrue(true, 'A deleted profile must not fail the queue.');
    }
}
