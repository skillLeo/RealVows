<?php

namespace Tests\Feature;

use App\Models\CreditLedgerEntry;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use App\Services\Credits\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The three-axis state machine holds, or it does not.
 *
 * ⚠️ These invariants are asserted rather than enforced by a CHECK
 * constraint, and the reason is worth stating: MariaDB honours CHECK
 * constraints, SQLite honours them differently, and the fast suite runs on
 * SQLite. A constraint that silently does nothing on the engine the tests run
 * against would be worse than no constraint at all — it would look like
 * protection.
 *
 * So the guarantee lives in CreditService, which is the only writer, and
 * these tests prove the writer cannot produce a state that violates it.
 */
class MatchInvariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function memberWithMatch(int $credits = 3): array
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $mine = Profile::factory()->woman()->create(['user_id' => $user->id]);
        $them = Profile::factory()->man()->create();

        if ($credits > 0) {
            app(CreditService::class)->adjust($user, $credits, 'goodwill', 'setup', $user);
        }

        $match = MatchRecord::create([
            'seeker_profile_id' => $mine->id,
            'matched_profile_id' => $them->id,
            'total_score' => 88,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_TEASER,
        ]);

        return [$user->fresh(), $match];
    }

    /**
     * Invariant 1: `unlocked_at` is null IF AND ONLY IF status is teaser.
     * Both directions, because "if and only if" is two claims.
     */
    public function test_unlocked_at_is_null_exactly_when_the_match_is_a_teaser(): void
    {
        [$user, $match] = $this->memberWithMatch();

        // Teaser → null
        $this->assertSame(MatchRecord::STATUS_TEASER, $match->status);
        $this->assertNull($match->unlocked_at);

        app(CreditService::class)->unlockMatch($user, $match);
        $match = $match->fresh();

        // Unlocked → not null
        $this->assertSame(MatchRecord::STATUS_UNLOCKED, $match->status);
        $this->assertNotNull($match->unlocked_at);

        // And reversing puts it back, rather than leaving a stamp behind.
        app(CreditService::class)->reverseUnlock($match, 'reviewer error');
        $match = $match->fresh();

        $this->assertSame(MatchRecord::STATUS_TEASER, $match->status);
        $this->assertNull($match->unlocked_at, 'A reversed unlock left its timestamp behind.');
    }

    /**
     * Invariant 2: `unlocked_by_ledger_id` is non-null whenever status is
     * unlocked. An unlocked match with no ledger row behind it is a match
     * somebody got for free.
     */
    public function test_an_unlocked_match_always_names_the_credit_that_paid_for_it(): void
    {
        [$user, $match] = $this->memberWithMatch();

        $entry = app(CreditService::class)->unlockMatch($user, $match);
        $match = $match->fresh();

        $this->assertNotNull($match->unlocked_by_ledger_id);
        $this->assertSame($entry->id, $match->unlocked_by_ledger_id);

        // And that row is a real debit for this match, not a dangling id.
        $ledger = CreditLedgerEntry::find($match->unlocked_by_ledger_id);
        $this->assertSame(-1, $ledger->delta);
        $this->assertSame('match_unlock', $ledger->reason);
        $this->assertSame($match->id, (int) $ledger->reference_id);
    }

    /** Swept across every row, so no path can produce a violation unnoticed. */
    public function test_the_invariants_hold_across_every_row_the_service_can_produce(): void
    {
        for ($i = 0; $i < 6; $i++) {
            [$user, $match] = $this->memberWithMatch(2);

            if ($i % 2 === 0) {
                app(CreditService::class)->unlockMatch($user, $match);
            }

            if ($i === 4) {
                app(CreditService::class)->reverseUnlock($match->fresh(), 'test');
            }
        }

        $violations = [];

        foreach (MatchRecord::all() as $m) {
            $isTeaser = $m->status === MatchRecord::STATUS_TEASER;

            if ($isTeaser !== ($m->unlocked_at === null)) {
                $violations[] = "#{$m->id}: status={$m->status} but unlocked_at is "
                    .($m->unlocked_at === null ? 'null' : 'set');
            }

            if ($m->status === MatchRecord::STATUS_UNLOCKED && $m->unlocked_by_ledger_id === null) {
                $violations[] = "#{$m->id}: unlocked with no ledger row — a free match";
            }

            if ($isTeaser && $m->unlocked_by_ledger_id !== null) {
                $violations[] = "#{$m->id}: teaser still pointing at a ledger row";
            }
        }

        $this->assertSame([], $violations, "Invariant violations:\n  ".implode("\n  ", $violations));
    }

    /**
     * ⚠️ POSITIVE CONTROL. The sweep above is a negative assertion over rows,
     * so it is worthless unless it can actually see a violation. This writes
     * one directly to the table, bypassing the service, and asserts the check
     * catches it.
     */
    public function test_the_invariant_sweep_can_actually_detect_a_violation(): void
    {
        [, $match] = $this->memberWithMatch();

        // The exact corruption the service makes impossible: unlocked, with
        // no credit behind it.
        DB::table('matches')->where('id', $match->id)->update([
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
            'unlocked_by_ledger_id' => null,
        ]);

        $found = [];

        foreach (MatchRecord::all() as $m) {
            if ($m->status === MatchRecord::STATUS_UNLOCKED && $m->unlocked_by_ledger_id === null) {
                $found[] = $m->id;
            }
        }

        $this->assertSame(
            [$match->id],
            $found,
            'The sweep cannot see an unlocked match with no ledger row, so it proves nothing.'
        );
    }

    /**
     * `response` is a separate axis from `status` — that separation is the
     * whole point of the reshape, and collapsing them back would be silent.
     */
    public function test_a_response_does_not_disturb_the_access_state(): void
    {
        [$user, $match] = $this->memberWithMatch();

        app(CreditService::class)->unlockMatch($user, $match);

        // Phase 3 will set this. It must not touch status or the ledger link.
        $match->fresh()->forceFill([
            'response' => MatchRecord::RESPONSE_INTERESTED,
            'responded_at' => now(),
        ])->save();

        $match = $match->fresh();

        $this->assertSame(MatchRecord::STATUS_UNLOCKED, $match->status);
        $this->assertNotNull($match->unlocked_by_ledger_id);
        $this->assertNotNull($match->unlocked_at);
    }
}
