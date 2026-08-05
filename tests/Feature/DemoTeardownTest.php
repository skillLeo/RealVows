<?php

namespace Tests\Feature;

use App\Models\CreditLedgerEntry;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ A DESTRUCTIVE COMMAND RUN ONCE, UNDER TIME PRESSURE, ON LAUNCH DAY.
 *
 * That is the worst possible combination, so every test here assumes the
 * operator is tired and wants it to be over. The guard matters more than the
 * deletion: getting the teardown wrong deletes real members, and there is no
 * undo for that.
 */
class DemoTeardownTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $demo): User
    {
        $user = User::factory()->create(['is_active' => true]);

        /*
         * ⚠️ forceFill, because `is_demo` is deliberately outside the Fillable
         * allow-list — a registration form able to post it would let an account
         * mark itself demo and be deleted by the teardown on launch day.
         */
        $user->forceFill(['is_demo' => $demo])->save();
        Profile::factory()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    // ── The refusal ──────────────────────────────────────────────────────────

    /**
     * ⚠️ MONEY HAVING CHANGED HANDS IS THE DEFINITIVE SIGNAL THAT AN ACCOUNT
     * IS REAL, whatever its flag says.
     *
     * A completed purchase on a supposedly-demo account means either a real
     * member is flagged demo or a demo account took a live payment. Neither is
     * resolved by deleting the evidence.
     */
    public function test_it_refuses_when_a_completed_purchase_would_be_touched(): void
    {
        $demo = $this->user(demo: true);

        Purchase::create([
            'user_id' => $demo->id,
            'package_key' => 'single',
            'credits_granted' => 1,
            'amount_cents' => 2000,
            'currency' => 'USD',
            'status' => 'completed',
            'order_identifier' => 'RV-REAL-0001',
        ]);

        $this->artisan('realvows:teardown-demo --execute --force')
            ->expectsOutputToContain('REFUSING')
            ->assertExitCode(1);

        $this->assertDatabaseHas('users', ['id' => $demo->id]);
    }

    /**
     * ⚠️ THE OVERRIDE IS EXPLICIT, TYPED, AND STILL WARNS.
     *
     * The guard cannot tell a seeded purchase from a real one, so the way past
     * it is a flag whose name says what it does — not a --force, and not a
     * quieter code path. It never looks like a clean run.
     */
    public function test_the_override_clears_demo_purchases_but_says_so(): void
    {
        $demo = $this->user(demo: true);

        Purchase::create([
            'user_id' => $demo->id,
            'package_key' => 'single',
            'credits_granted' => 1,
            'amount_cents' => 2000,
            'currency' => 'USD',
            'status' => 'completed',
            'order_identifier' => 'RV-SEEDED-0001',
        ]);

        $this->artisan('realvows:teardown-demo --execute --force --clear-demo-purchases')
            ->expectsOutputToContain('will be deleted with these accounts')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('users', ['id' => $demo->id]);
        $this->assertSame(0, Purchase::count());
    }

    /** ⚠️ POSITIVE CONTROL: --force alone is NOT enough to get past the guard. */
    public function test_force_alone_does_not_bypass_the_purchase_guard(): void
    {
        $demo = $this->user(demo: true);

        Purchase::create([
            'user_id' => $demo->id,
            'package_key' => 'single',
            'credits_granted' => 1,
            'amount_cents' => 2000,
            'currency' => 'USD',
            'status' => 'completed',
            'order_identifier' => 'RV-SEEDED-0002',
        ]);

        $this->artisan('realvows:teardown-demo --execute --force')
            ->expectsOutputToContain('REFUSING')
            ->assertExitCode(1);

        $this->assertDatabaseHas('users', ['id' => $demo->id]);
    }

    /** ⚠️ POSITIVE CONTROL: it does proceed when no money is involved. */
    public function test_it_proceeds_when_no_completed_purchase_exists(): void
    {
        $demo = $this->user(demo: true);

        $this->artisan('realvows:teardown-demo --execute --force')->assertExitCode(0);

        $this->assertDatabaseMissing('users', ['id' => $demo->id]);
    }

    // ── The blast radius ─────────────────────────────────────────────────────

    /** ⚠️ THE TEST THAT MATTERS. Real members survive, untouched. */
    public function test_real_members_are_never_deleted(): void
    {
        $real = $this->user(demo: false);
        $demo = $this->user(demo: true);

        $this->artisan('realvows:teardown-demo --execute --force')->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => $real->id]);
        $this->assertDatabaseHas('profiles', ['user_id' => $real->id]);
        $this->assertDatabaseMissing('users', ['id' => $demo->id]);
    }

    /**
     * ⚠️ AN INTRODUCTION FROM A REAL MEMBER TO A DEMO ONE MUST GO TOO.
     *
     * Removed in one direction only, a real member is left holding an
     * introduction to somebody who no longer exists — a card that renders
     * blank, or throws.
     */
    public function test_an_introduction_pointing_at_a_demo_member_is_removed(): void
    {
        $real = $this->user(demo: false);
        $demo = $this->user(demo: true);

        $match = MatchRecord::create([
            'seeker_profile_id' => $real->profile->id,
            'matched_profile_id' => $demo->profile->id,
            'total_score' => 80,
            'score_breakdown' => ['components' => []],
            'compatibility_rationale' => 'A steady match.',
            'commonalities' => [],
            'status' => MatchRecord::STATUS_TEASER,
        ]);

        $this->artisan('realvows:teardown-demo --execute --force')->assertExitCode(0);

        $this->assertDatabaseMissing('matches', ['id' => $match->id]);
        $this->assertDatabaseHas('users', ['id' => $real->id]);
    }

    /** A real member's own ledger is untouched. */
    public function test_a_real_members_ledger_survives(): void
    {
        $real = $this->user(demo: false);
        $this->user(demo: true);

        $entry = CreditLedgerEntry::create([
            'user_id' => $real->id,
            'delta' => 3,
            'balance_after' => 3,
            'reason' => 'goodwill',
        ]);

        $this->artisan('realvows:teardown-demo --execute --force')->assertExitCode(0);

        $this->assertDatabaseHas('credit_ledger', ['id' => $entry->id]);
    }

    // ── The dry run ──────────────────────────────────────────────────────────

    /** ⚠️ DEFAULTS TO A DRY RUN. --execute is required to delete anything. */
    public function test_without_execute_it_changes_nothing(): void
    {
        $demo = $this->user(demo: true);

        $this->artisan('realvows:teardown-demo')
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => $demo->id]);
    }

    /** Nothing to do is said out loud rather than reported as success. */
    public function test_it_says_so_when_there_is_nothing_to_remove(): void
    {
        $this->user(demo: false);

        $this->artisan('realvows:teardown-demo --execute --force')
            ->expectsOutputToContain('No demo members found')
            ->assertExitCode(0);
    }

    /**
     * ⚠️ THE MARKER, NOT A PATTERN.
     *
     * Matching on `@example.com` is how a real member at `x@example.com.jm`
     * gets deleted on launch day. An account without the marker is never
     * eligible, whatever its email looks like.
     */
    public function test_an_unmarked_account_with_a_demo_looking_email_survives(): void
    {
        $lookalike = User::factory()->create(['email' => 'someone@example.com.jm']);
        $lookalike->forceFill(['is_demo' => false])->save();
        Profile::factory()->create(['user_id' => $lookalike->id]);

        $this->user(demo: true);

        $this->artisan('realvows:teardown-demo --execute --force')->assertExitCode(0);

        $this->assertNotNull(
            User::find($lookalike->id),
            'An account was deleted for LOOKING like a demo account rather than being marked as '
                .'one. That is how a real member at x@example.com.jm dies on launch day.'
        );
    }
}
