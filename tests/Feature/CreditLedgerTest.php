<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientCredits;
use App\Exceptions\MatchAlreadyUnlocked;
use App\Models\CreditLedgerEntry;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Payments\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The money tests.
 *
 * ⚠️ These run on SQLite, where lockForUpdate() is a NO-OP. That is
 * deliberate and it is the point: if these pass here, the guarantee is
 * coming from the unique indexes rather than from the locks, which is the
 * only way it holds under a database that retries a transaction or a worker
 * that runs twice.
 *
 * A green concurrency test on SQLite would prove nothing if the lock were
 * doing the work. It proves something because the lock is not.
 */
class CreditLedgerTest extends TestCase
{
    use RefreshDatabase;

    private CreditService $credits;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $this->credits = app(CreditService::class);
        Queue::fake();
        Mail::fake();
    }

    private function purchase(User $user, array $overrides = []): Purchase
    {
        return Purchase::create([
            'user_id' => $user->id,
            'package_key' => 'triple',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'credits_granted' => 3,
            'status' => Purchase::STATUS_PENDING_3DS,
            'gateway' => 'powertranz',
            'gateway_mode' => 'sandbox',
            'order_identifier' => (string) Str::uuid(),
            ...$overrides,
        ]);
    }

    private function memberWithMatch(int $credits = 0): array
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $profile = Profile::factory()->woman()->create(['user_id' => $user->id]);
        $them = Profile::factory()->man()->create();

        if ($credits > 0) {
            $this->credits->adjust($user, $credits, 'goodwill', 'Test setup', $user);
        }

        $match = MatchRecord::create([
            'seeker_profile_id' => $profile->id,
            'matched_profile_id' => $them->id,
            'total_score' => 88,
            'score_breakdown' => ['components' => []],
            'status' => MatchRecord::STATUS_TEASER,
        ]);

        return [$user->fresh(), $match];
    }

    // ── Balance is derived, always ───────────────────────────────────────

    public function test_the_balance_is_always_the_sum_of_the_ledger(): void
    {
        $user = User::factory()->create();

        $this->credits->adjust($user, 8, 'goodwill', 'Launch gift', $user);
        $this->credits->adjust($user, -3, 'admin_adjustment', 'Correction', $user);
        $this->credits->adjust($user, 2, 'goodwill', 'Apology', $user);

        $this->assertSame(7, $user->fresh()->creditBalance());
        $this->assertSame(7, (int) CreditLedgerEntry::where('user_id', $user->id)->sum('delta'));
    }

    public function test_the_running_balance_column_agrees_with_the_derived_balance(): void
    {
        $user = User::factory()->create();

        foreach ([5, -1, -1, 3, -2] as $delta) {
            $delta > 0
                ? $this->credits->adjust($user, $delta, 'goodwill', 'x', $user)
                : $this->credits->adjust($user, $delta, 'admin_adjustment', 'x', $user);
        }

        $running = 0;
        foreach (CreditLedgerEntry::where('user_id', $user->id)->orderBy('id')->get() as $entry) {
            $running += $entry->delta;
            $this->assertSame($running, $entry->balance_after, "Chain broke at ledger row {$entry->id}.");
        }

        $this->assertSame($running, $user->fresh()->creditBalance());
    }

    public function test_the_integrity_command_passes_on_a_healthy_ledger(): void
    {
        $user = User::factory()->create();
        $this->credits->adjust($user, 5, 'goodwill', 'x', $user);

        $this->artisan('realvows:verify-ledger')->assertSuccessful();
    }

    /** ⚠️ The command must actually detect tampering, or it is decoration. */
    public function test_the_integrity_command_detects_a_tampered_row(): void
    {
        $user = User::factory()->create();
        $entry = $this->credits->adjust($user, 5, 'goodwill', 'x', $user);

        // Simulate somebody editing the database directly.
        \DB::table('credit_ledger')->where('id', $entry->id)->update(['balance_after' => 999]);

        $this->artisan('realvows:verify-ledger')->assertFailed();
    }

    // ── Granting ─────────────────────────────────────────────────────────

    public function test_a_completed_purchase_grants_exactly_its_credits(): void
    {
        $user = User::factory()->create();
        $purchase = $this->purchase($user, ['gateway_transaction_id' => 'txn-1']);

        $this->credits->grantForPurchase($purchase);

        $this->assertSame(3, $user->fresh()->creditBalance());
        $this->assertSame(Purchase::STATUS_COMPLETED, $purchase->fresh()->status);
    }

    /**
     * ⚠️ THE GUARANTEE. A retried gateway callback is normal, not
     * exceptional, and must never credit twice.
     */
    public function test_a_duplicate_callback_never_double_credits(): void
    {
        $user = User::factory()->create();
        $purchase = $this->purchase($user, ['gateway_transaction_id' => 'txn-dup']);

        for ($i = 0; $i < 5; $i++) {
            $this->credits->grantForPurchase($purchase->fresh());
        }

        $this->assertSame(3, $user->fresh()->creditBalance(), 'Credits were granted more than once.');
        $this->assertSame(1, CreditLedgerEntry::where('user_id', $user->id)->count());
    }

    public function test_the_unique_index_and_not_the_lock_is_what_stops_the_double_credit(): void
    {
        $user = User::factory()->create();
        $purchase = $this->purchase($user, ['gateway_transaction_id' => 'txn-race']);

        $this->credits->grantForPurchase($purchase);

        // Bypass the service entirely — this is what a second worker racing
        // past the in-transaction read would attempt.
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        CreditLedgerEntry::create([
            'user_id' => $user->id,
            'delta' => 3,
            'balance_after' => 6,
            'reason' => 'purchase',
            'reference_type' => Purchase::class,
            'reference_id' => $purchase->id,
            'gateway_transaction_id' => 'txn-race',
        ]);
    }

    public function test_granting_without_a_transaction_id_is_refused(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->credits->grantForPurchase($this->purchase($user));
    }

    // ── Spending ─────────────────────────────────────────────────────────

    public function test_unlocking_spends_exactly_one_credit(): void
    {
        [$user, $match] = $this->memberWithMatch(3);

        $entry = $this->credits->unlockMatch($user, $match);

        $this->assertSame(2, $user->fresh()->creditBalance());
        $this->assertSame(-1, $entry->delta);

        $match = $match->fresh();
        $this->assertSame(MatchRecord::STATUS_UNLOCKED, $match->status);
        $this->assertNotNull($match->unlocked_at);
        $this->assertSame($entry->id, $match->unlocked_by_ledger_id);
    }

    public function test_unlocking_without_credits_is_refused_and_charges_nothing(): void
    {
        [$user, $match] = $this->memberWithMatch(0);

        try {
            $this->credits->unlockMatch($user, $match);
            $this->fail('Expected InsufficientCredits.');
        } catch (InsufficientCredits) {
            // expected
        }

        $this->assertSame(0, $user->fresh()->creditBalance());
        $this->assertSame(MatchRecord::STATUS_TEASER, $match->fresh()->status);
        $this->assertSame(0, CreditLedgerEntry::where('user_id', $user->id)->where('reason', 'match_unlock')->count());
    }

    /**
     * ⚠️ TWO TABS, ONE CREDIT. Exactly one unlock and one kind error — never
     * two unlocks, and never a lost update giving away a free match.
     *
     * The second attempt is made to bypass the in-transaction status read,
     * which is what a genuinely concurrent request would do.
     */
    public function test_two_simultaneous_unlocks_with_one_credit_yield_exactly_one_unlock(): void
    {
        [$user, $match] = $this->memberWithMatch(1);

        $this->credits->unlockMatch($user, $match);

        // The losing tab: still holding its stale copy showing a teaser.
        $stale = MatchRecord::whereKey($match->id)->first();
        $stale->setRawAttributes([...$stale->getAttributes(), 'status' => MatchRecord::STATUS_TEASER], true);

        $this->expectException(MatchAlreadyUnlocked::class);

        try {
            $this->credits->unlockMatch($user->fresh(), $stale);
        } finally {
            $this->assertSame(0, $user->fresh()->creditBalance(), 'The member was charged twice.');
            $this->assertSame(
                1,
                CreditLedgerEntry::where('reason', 'match_unlock')->where('reference_id', $match->id)->count(),
                'Two charges exist for one introduction.'
            );
        }
    }

    /** The constraint, proven directly. */
    public function test_the_database_refuses_a_second_charge_for_the_same_introduction(): void
    {
        [$user, $match] = $this->memberWithMatch(5);

        $this->credits->unlockMatch($user, $match);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        CreditLedgerEntry::create([
            'user_id' => $user->id,
            'delta' => -1,
            'balance_after' => 3,
            'reason' => 'match_unlock',
            'reference_type' => MatchRecord::class,
            'reference_id' => $match->id,
        ]);
    }

    public function test_a_reversal_is_a_new_row_and_never_a_deletion(): void
    {
        [$user, $match] = $this->memberWithMatch(2);

        $this->credits->unlockMatch($user, $match);
        $this->assertSame(1, $user->fresh()->creditBalance());

        $this->credits->reverseUnlock($match->fresh(), 'Reviewer error');

        $this->assertSame(2, $user->fresh()->creditBalance());
        $this->assertSame(MatchRecord::STATUS_TEASER, $match->fresh()->status);

        // The original spend is still on the record.
        $this->assertSame(1, CreditLedgerEntry::where('reason', 'match_unlock')->count());
        $this->assertSame(1, CreditLedgerEntry::where('reason', 'unlock_reversed')->count());
    }

    public function test_a_balance_can_never_be_driven_negative_by_an_adjustment(): void
    {
        $user = User::factory()->create();
        $this->credits->adjust($user, 2, 'goodwill', 'x', $user);

        $this->expectException(InsufficientCredits::class);

        $this->credits->adjust($user, -5, 'admin_adjustment', 'Too much', $user);
    }

    public function test_an_adjustment_must_say_why(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->credits->adjust($user, 5, 'admin_adjustment', '   ', $user);
    }

    public function test_an_adjustment_records_who_made_it(): void
    {
        $user = User::factory()->create();
        $staff = User::factory()->create();

        $entry = $this->credits->adjust($user, 5, 'goodwill', 'Apology for the delay', $staff);

        $this->assertSame($staff->id, $entry->created_by);
        $this->assertSame('Apology for the delay', $entry->note);
    }

    // ── Settlement, end to end against FakeGateway ───────────────────────

    public function test_a_failed_payment_credits_nothing(): void
    {
        $user = User::factory()->create();
        // FakeGateway declines anything ending in 01.
        $purchase = $this->purchase($user, ['amount_cents' => 5001]);

        app(PurchaseService::class)->settle($purchase);

        $this->assertSame(0, $user->fresh()->creditBalance());
        $this->assertSame(Purchase::STATUS_FAILED, $purchase->fresh()->status);
    }

    public function test_settling_twice_credits_once(): void
    {
        $user = User::factory()->create();
        $purchase = $this->purchase($user);
        $service = app(PurchaseService::class);

        $service->settle($purchase);
        $service->settle($purchase->fresh());
        $service->settle($purchase->fresh());

        $this->assertSame(3, $user->fresh()->creditBalance());
    }

    public function test_an_out_of_order_callback_for_a_completed_purchase_is_harmless(): void
    {
        $user = User::factory()->create();
        $purchase = $this->purchase($user);
        $service = app(PurchaseService::class);

        $service->settle($purchase);
        $balance = $user->fresh()->creditBalance();

        // The late one.
        $service->settle($purchase->fresh());

        $this->assertSame($balance, $user->fresh()->creditBalance());
    }

    public function test_a_purchase_the_gateway_says_is_still_pending_is_not_credited(): void
    {
        $user = User::factory()->create();
        // FakeGateway keeps anything ending 03 pending.
        $purchase = $this->purchase($user, ['amount_cents' => 5003]);

        app(PurchaseService::class)->settle($purchase);

        $this->assertSame(0, $user->fresh()->creditBalance());
        $this->assertSame(Purchase::STATUS_PENDING_3DS, $purchase->fresh()->status);
    }

    public function test_a_completed_purchase_never_leaves_a_ledger_row_missing(): void
    {
        $user = User::factory()->create();
        app(PurchaseService::class)->settle($this->purchase($user));

        foreach (Purchase::where('status', Purchase::STATUS_COMPLETED)->get() as $completed) {
            $this->assertSame(
                1,
                CreditLedgerEntry::where('reference_type', Purchase::class)
                    ->where('reference_id', $completed->id)->count(),
                "Purchase #{$completed->id} is completed with no ledger row behind it."
            );
        }
    }
}
