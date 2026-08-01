<?php

namespace Tests\Feature;

use App\Mail\MatchWithdrawn;
use App\Models\CreditLedgerEntry;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use App\Models\Verification;
use App\Services\Credits\CreditService;
use App\Services\Matching\MatchLifecycle;
use App\Services\Verification\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MatchLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        Mail::fake();
        Queue::fake();
    }

    /**
     * @return array{0: User, 1: MatchRecord, 2: Profile}
     */
    private function paidIntroduction(): array
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $mine = Profile::factory()->woman()->create(['user_id' => $user->id]);
        $them = Profile::factory()->man()->create();
        Verification::factory()->verified()->create(['profile_id' => $them->id]);

        $match = MatchRecord::create([
            'seeker_profile_id' => $mine->id,
            'matched_profile_id' => $them->id,
            'total_score' => 88,
            'score_breakdown' => [],
            'status' => MatchRecord::STATUS_TEASER,
        ]);

        app(CreditService::class)->adjust($user, 2, 'goodwill', 'setup', $user);
        app(CreditService::class)->unlockMatch($user->fresh(), $match);

        return [$user->fresh(), $match->fresh(), $them];
    }

    // ── Credit reversal ──────────────────────────────────────────────────

    /**
     * ⚠️ They paid for an introduction to a VERIFIED member. If we can no
     * longer stand behind that person, the credit comes back without them
     * having to notice, ask, or argue.
     */
    public function test_withdrawing_a_paid_introduction_returns_the_credit(): void
    {
        [$user, $match, $them] = $this->paidIntroduction();

        $this->assertSame(1, $user->creditBalance());

        app(MatchLifecycle::class)->withdrawIntroductionsTo($them, 'suspended');

        $this->assertSame(2, $user->fresh()->creditBalance(), 'The credit did not come back.');
        $this->assertSame(MatchRecord::STATUS_EXPIRED, $match->fresh()->status);
        $this->assertNull($match->fresh()->unlocked_at);
    }

    /** A new positive row, never a deletion of the original spend. */
    public function test_the_reversal_is_appended_and_the_original_spend_survives(): void
    {
        [$user, , $them] = $this->paidIntroduction();

        app(MatchLifecycle::class)->withdrawIntroductionsTo($them, 'suspended');

        $this->assertSame(1, CreditLedgerEntry::where('reason', 'match_unlock')->count());
        $this->assertSame(1, CreditLedgerEntry::where('reason', 'unlock_reversed')->count());
    }

    public function test_a_locked_teaser_is_withdrawn_without_a_refund_or_an_email(): void
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $mine = Profile::factory()->woman()->create(['user_id' => $user->id]);
        $them = Profile::factory()->man()->create();

        $match = MatchRecord::create([
            'seeker_profile_id' => $mine->id,
            'matched_profile_id' => $them->id,
            'total_score' => 80,
            'score_breakdown' => [],
            'status' => MatchRecord::STATUS_TEASER,
        ]);

        app(MatchLifecycle::class)->withdrawIntroductionsTo($them, 'suspended');

        $this->assertSame(MatchRecord::STATUS_EXPIRED, $match->fresh()->status);
        $this->assertSame(0, $user->fresh()->creditBalance());

        /*
         * Nothing was paid, so there is nothing to say. Telling somebody a
         * locked card went away is drawing attention to nothing.
         *
         * Both channels asserted: the mailable is ShouldQueue, so
         * assertNothingSent() alone would pass even if one HAD gone out.
         */
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_only_the_member_who_paid_is_told(): void
    {
        [, , $them] = $this->paidIntroduction();

        app(MatchLifecycle::class)->withdrawIntroductionsTo($them, 'suspended');

        // Queued, not sent — the mailable is ShouldQueue.
        Mail::assertQueued(MatchWithdrawn::class, 1);
    }

    /** ⚠️ The email never says why. That is not the member's business. */
    public function test_the_withdrawal_email_does_not_explain_why(): void
    {
        [, , $them] = $this->paidIntroduction();

        app(MatchLifecycle::class)->withdrawIntroductionsTo($them, 'failed a re-check for fraud');

        Mail::assertQueued(MatchWithdrawn::class, function (MatchWithdrawn $mail) {
            $body = $mail->render();

            foreach (['fraud', 'suspend', 'banned', 'removed for', 're-check'] as $term) {
                $this->assertStringNotContainsStringIgnoringCase($term, $body);
            }

            return true;
        });
    }

    /** The trigger, through the real reviewer path. */
    public function test_losing_verification_withdraws_introductions_and_returns_credits(): void
    {
        [$user, $match, $them] = $this->paidIntroduction();

        $reviewer = User::factory()->create();
        $reviewer->assignRole('super_admin');

        app(VerificationService::class)->reject(
            $them->verification,
            $reviewer,
            'The photos do not appear to show the same person'
        );

        $this->assertSame(2, $user->fresh()->creditBalance());
        $this->assertSame(MatchRecord::STATUS_EXPIRED, $match->fresh()->status);
    }

    // ── Teaser expiry ────────────────────────────────────────────────────

    /** ⚠️ Teasers go stale at 90 days. Unlocked matches never expire. */
    public function test_stale_teasers_expire_but_paid_introductions_never_do(): void
    {
        [$user, $paid] = $this->paidIntroduction();

        $mine = $user->profile;
        $old = Profile::factory()->man()->create();
        $recent = Profile::factory()->man()->create();

        $staleTeaser = MatchRecord::create([
            'seeker_profile_id' => $mine->id, 'matched_profile_id' => $old->id,
            'total_score' => 80, 'score_breakdown' => [], 'status' => MatchRecord::STATUS_TEASER,
            'generated_at' => now()->subDays(91),
        ]);

        $freshTeaser = MatchRecord::create([
            'seeker_profile_id' => $mine->id, 'matched_profile_id' => $recent->id,
            'total_score' => 80, 'score_breakdown' => [], 'status' => MatchRecord::STATUS_TEASER,
            'generated_at' => now()->subDays(30),
        ]);

        // The paid one is made ancient on purpose — it must still survive.
        $paid->forceFill(['generated_at' => now()->subDays(400)])->save();

        $expired = app(MatchLifecycle::class)->expireStaleTeasers();

        $this->assertSame(1, $expired);
        $this->assertSame(MatchRecord::STATUS_EXPIRED, $staleTeaser->fresh()->status);
        $this->assertSame(MatchRecord::STATUS_TEASER, $freshTeaser->fresh()->status);
        $this->assertSame(
            MatchRecord::STATUS_UNLOCKED,
            $paid->fresh()->status,
            'A paid introduction expired. It was bought and it is permanent.'
        );
    }

    public function test_expiry_is_silent(): void
    {
        $mine = Profile::factory()->woman()->create();
        $them = Profile::factory()->man()->create();

        MatchRecord::create([
            'seeker_profile_id' => $mine->id, 'matched_profile_id' => $them->id,
            'total_score' => 80, 'score_breakdown' => [], 'status' => MatchRecord::STATUS_TEASER,
            'generated_at' => now()->subDays(100),
        ]);

        app(MatchLifecycle::class)->expireStaleTeasers();

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    /**
     * ⚠️ Order matters in the nightly sweep. Topping up before expiring would
     * count stale teasers as filling the set, generate nothing, and leave the
     * member with the same three-month-old people while reporting success.
     */
    public function test_the_nightly_sweep_expires_before_it_tops_up(): void
    {
        $mine = Profile::factory()->woman()->create();
        Verification::factory()->verified()->create(['profile_id' => $mine->id]);

        for ($i = 0; $i < 8; $i++) {
            MatchRecord::create([
                'seeker_profile_id' => $mine->id,
                'matched_profile_id' => Profile::factory()->man()->create()->id,
                'total_score' => 80, 'score_breakdown' => [], 'status' => MatchRecord::STATUS_TEASER,
                'generated_at' => now()->subDays(120),
            ]);
        }

        $this->artisan('realvows:refresh-matches')
            ->expectsOutputToContain('Expired 8 stale introductions.')
            ->assertSuccessful();

        $this->assertSame(
            0,
            MatchRecord::where('seeker_profile_id', $mine->id)
                ->where('status', MatchRecord::STATUS_TEASER)->count()
        );
    }

    /** Expiry must not touch a member's balance. */
    public function test_expiry_never_moves_credits(): void
    {
        [$user] = $this->paidIntroduction();
        $before = $user->creditBalance();

        app(MatchLifecycle::class)->expireStaleTeasers();

        $this->assertSame($before, $user->fresh()->creditBalance());
    }
}
