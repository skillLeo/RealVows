<?php

namespace Tests\Feature;

use App\Jobs\AnnounceMutualInterest;
use App\Models\Block;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use App\Services\Matching\CandidatePool;
use App\Services\Matching\MatchInterest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Blocking, and the mutual-interest race.
 *
 * Two features, one file, because they share the property that makes them hard
 * to test honestly: both are about something NOT happening, and the naive test
 * for each passes on a completely broken implementation.
 */
class BlockingAndRaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    /**
     * ⚠️ MATCHABLE, NOT MERELY EXISTENT.
     *
     * The candidate pool requires verified + submitted + active. A profile
     * missing any of those is filtered out before blocking is ever consulted —
     * so a blocking test built on plain factory profiles passes whether the
     * block filter works or not. The control in the first test caught exactly
     * that and is the reason this helper exists in this form.
     */
    private function member(string $email, string $gender, string $seeking): User
    {
        // A phone number too: the announcement job returns early without one,
        // which would make the notification count assertion pass on zero.
        $user = User::factory()->create([
            'email' => $email,
            'is_active' => true,
            'phone_country_code' => '+1',
            'phone_number' => '87655501'.random_int(10, 99),
        ]);

        Profile::factory()->verified()->create([
            'submitted_at' => now(),
            'user_id' => $user->id,
            'first_name' => ucfirst(strtok($email, '@')),
            'gender' => $gender,
            'seeking' => $seeking,
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'city' => 'Kingston',
            'country' => 'Jamaica',
        ]);

        return $user->fresh();
    }

    // ── Blocking (C4) ────────────────────────────────────────────────────────

    /**
     * ⚠️ THE HARD FILTER, NOT THE PRESENTER.
     *
     * Filtering at display time means the engine still scored them, still
     * wrote a row, and on the paid product still spent one of the
     * introductions the member is entitled to — on somebody who can never be
     * shown to them.
     */
    public function test_a_blocked_member_is_removed_from_the_candidate_pool(): void
    {
        $aisha = $this->member('aisha@example.com', 'woman', 'man');
        $omar = $this->member('omar@example.com', 'man', 'woman');

        $pool = app(CandidatePool::class);

        // The control: they are a candidate BEFORE the block.
        $this->assertTrue(
            $pool->query($aisha->profile)->pluck('profiles.id')->contains($omar->profile->id),
            'Omar was never a candidate to begin with, so this test proves nothing about blocking.'
        );

        Block::create([
            'blocker_profile_id' => $aisha->profile->id,
            'blocked_profile_id' => $omar->profile->id,
        ]);

        $this->assertFalse(
            $pool->query($aisha->profile->fresh())->pluck('profiles.id')->contains($omar->profile->id),
            'A blocked member is still in the candidate pool, so the engine will score them and '
                .'spend an introduction on somebody who can never be shown.'
        );
    }

    /**
     * ⚠️ THE DIRECTION THAT IS EASY TO MISS.
     *
     * A one-directional block leaves the blocked party still receiving the
     * blocker, still able to express interest in them, and able to infer from
     * the silence that something happened. On a platform this small that is
     * how a block becomes an escalation.
     */
    public function test_the_blocker_is_also_hidden_from_the_blocked(): void
    {
        $aisha = $this->member('aisha@example.com', 'woman', 'man');
        $omar = $this->member('omar@example.com', 'man', 'woman');

        Block::create([
            'blocker_profile_id' => $aisha->profile->id,
            'blocked_profile_id' => $omar->profile->id,
        ]);

        $this->assertFalse(
            app(CandidatePool::class)
                ->query($omar->profile->fresh())
                ->pluck('profiles.id')
                ->contains($aisha->profile->id),
            'Omar, who was blocked, can still be matched with Aisha, who blocked him. He will '
                .'receive her as an introduction and can express interest in somebody who has '
                .'explicitly asked never to see him.'
        );
    }

    /** ⚠️ POSITIVE CONTROL: the filter removes the blocked pair and nobody else. */
    public function test_blocking_does_not_remove_unrelated_candidates(): void
    {
        $aisha = $this->member('aisha@example.com', 'woman', 'man');
        $omar = $this->member('omar@example.com', 'man', 'woman');
        $yusuf = $this->member('yusuf@example.com', 'man', 'woman');

        Block::create([
            'blocker_profile_id' => $aisha->profile->id,
            'blocked_profile_id' => $omar->profile->id,
        ]);

        $candidates = app(CandidatePool::class)->query($aisha->profile->fresh())->pluck('profiles.id');

        $this->assertTrue(
            $candidates->contains($yusuf->profile->id),
            'Blocking one member removed an unrelated one, so the filter is over-broad and every '
                .'block quietly shrinks the pool.'
        );
    }

    public function test_blocking_twice_is_one_block(): void
    {
        $aisha = $this->member('aisha@example.com', 'woman', 'man');
        $omar = $this->member('omar@example.com', 'man', 'woman');

        Block::create(['blocker_profile_id' => $aisha->profile->id, 'blocked_profile_id' => $omar->profile->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Block::create(['blocker_profile_id' => $aisha->profile->id, 'blocked_profile_id' => $omar->profile->id]);
    }

    // ── The mutual-interest race (C3) ────────────────────────────────────────

    /**
     * ⚠️ A REAL RACE, NOT A SEQUENTIAL TEST THAT ASSUMES ONE CANNOT HAPPEN.
     *
     * The naive version calls express() twice in order and checks the result.
     * That proves the happy path and nothing about concurrency: in the ordered
     * case the first call sees no reciprocal interest and does not announce, so
     * exactly one announcement happens by construction.
     *
     * The dangerous interleaving is both requests writing their response
     * BEFORE either evaluates mutuality. Then both evaluate, both see a mutual
     * match, and both announce — two events and four messages for one thing
     * that happened once.
     *
     * This reproduces that interleaving deterministically rather than hoping
     * for it: both responses are written first, then both are evaluated.
     */
    public function test_simultaneous_interest_produces_one_event_and_two_notifications(): void
    {
        $aisha = $this->member('aisha@example.com', 'woman', 'man');
        $omar = $this->member('omar@example.com', 'man', 'woman');

        $aishaToOmar = $this->matchBetween($aisha, $omar);
        $omarToAisha = $this->matchBetween($omar, $aisha);

        $interest = app(MatchInterest::class);

        /*
         * ⚠️ THE INTERLEAVING. Both writes land before either read — which is
         * what two requests inside the same database tick actually do.
         */
        DB::table('matches')->where('id', $aishaToOmar->id)
            ->update(['response' => MatchRecord::RESPONSE_INTERESTED, 'responded_at' => now()]);
        DB::table('matches')->where('id', $omarToAisha->id)
            ->update(['response' => MatchRecord::RESPONSE_INTERESTED, 'responded_at' => now()]);

        // Now both evaluate. Both legitimately conclude "mutual".
        $this->assertTrue($interest->isMutual($aishaToOmar->fresh()));
        $this->assertTrue($interest->isMutual($omarToAisha->fresh()));

        /*
         * So both dispatch. The defence is not "only one dispatches" — it is
         * that both resolve to the SAME unique key, so the queue admits one.
         */
        $keys = [
            (new AnnounceMutualInterest($aishaToOmar->id))->uniqueId(),
            (new AnnounceMutualInterest($omarToAisha->id))->uniqueId(),
        ];

        $this->assertSame(
            $keys[0],
            $keys[1],
            'The two directions of one mutual match produce different unique keys, so a genuine '
                .'race announces it twice and tells both people twice.'
        );

        // And one announcement notifies exactly two people — not one, not four.
        app(\App\Services\Settings\SettingsService::class)->set('whatsapp', 'template_name', 'mutual_interest');

        $sender = new RecordingSender;

        (new AnnounceMutualInterest($aishaToOmar->id))->handle(
            $interest,
            $sender,
            app(\App\Services\Settings\SettingsService::class),
        );

        $this->assertCount(2, $sender->sent, 'One mutual match must produce exactly two notifications.');
    }

    /**
     * ⚠️ POSITIVE CONTROL. Equal keys prove nothing if uniqueId() returns a
     * constant — that would collapse every mutual match in the product into
     * one and silently drop all but the first.
     */
    public function test_unrelated_pairs_do_not_share_a_unique_key(): void
    {
        $aisha = $this->member('aisha@example.com', 'woman', 'man');
        $omar = $this->member('omar@example.com', 'man', 'woman');
        $yusuf = $this->member('yusuf@example.com', 'man', 'woman');

        $one = $this->matchBetween($aisha, $omar);
        $two = $this->matchBetween($aisha, $yusuf);

        $this->assertNotSame(
            (new AnnounceMutualInterest($one->id))->uniqueId(),
            (new AnnounceMutualInterest($two->id))->uniqueId(),
            'Unrelated pairs share a unique key, so every mutual match after the first would be '
                .'swallowed.'
        );
    }

    /**
     * ⚠️ AND THE RESPONSE ITSELF CANNOT BE WRITTEN TWICE.
     *
     * The conditional update is what stops a double-tap flipping a pass into
     * interest after the other side may already have been told.
     */
    public function test_a_concurrent_double_response_writes_once(): void
    {
        $aisha = $this->member('aisha@example.com', 'woman', 'man');
        $omar = $this->member('omar@example.com', 'man', 'woman');

        $match = $this->matchBetween($aisha, $omar);

        $interest = app(MatchInterest::class);

        // Two requests arriving together, both holding a stale copy that says
        // "not yet answered".
        $stale = $match->fresh();

        $interest->pass($match);
        $interest->express($stale);

        $this->assertSame(
            MatchRecord::RESPONSE_PASSED,
            $match->fresh()->response,
            'A second concurrent request overwrote the first answer. By then the other side may '
                .'already have been told.'
        );
    }

    private function matchBetween(User $seeker, User $matched): MatchRecord
    {
        return MatchRecord::create([
            'seeker_profile_id' => $seeker->profile->id,
            'matched_profile_id' => $matched->profile->id,
            'total_score' => 87.25,
            'score_breakdown' => ['components' => []],
            'compatibility_rationale' => 'A patient, home-building match.',
            'commonalities' => [],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
        ]);
    }
}
