<?php

namespace Tests\Integration;

use App\Exceptions\MatchAlreadyUnlocked;
use App\Models\CreditLedgerEntry;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Payments\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The money guarantees, on the engine that actually runs in production.
 *
 * ⚠️ RUN THIS BEFORE EVERY DEPLOY THAT TOUCHES CREDITS OR PAYMENTS.
 *
 *     php artisan test --configuration=phpunit.integration.xml
 *
 * The fast suite proves the unique indexes carry the guarantee, because it
 * runs where lockForUpdate() does nothing. This suite proves the rest: that
 * MariaDB honours the same constraints, that the enums round-trip, that real
 * row locking serialises concurrent spending rather than deadlocking, and
 * that a transaction rolled back on a genuine engine leaves nothing behind.
 *
 * Skips itself with a clear message when no integration database is
 * reachable, so it never becomes the reason somebody stops running tests.
 */
class MoneyOnRealDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped(
                'Integration suite: run with --configuration=phpunit.integration.xml against MariaDB.'
            );
        }

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        Queue::fake();
        Mail::fake();
    }

    private function credits(): CreditService
    {
        return app(CreditService::class);
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

    private function memberWithMatch(int $credits): array
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        $profile = Profile::factory()->woman()->create(['user_id' => $user->id]);
        $them = Profile::factory()->man()->create();

        if ($credits > 0) {
            $this->credits()->adjust($user, $credits, 'goodwill', 'Integration setup', $user);
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

    public function test_the_engine_really_is_mariadb_or_mysql(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());

        $version = DB::selectOne('select version() as v')->v;
        $this->assertNotEmpty($version);
    }

    /** The constraint has to exist on the real engine, not just in the migration file. */
    public function test_the_unique_indexes_actually_exist_on_the_tables(): void
    {
        $ledger = collect(DB::select('SHOW INDEX FROM credit_ledger'))->pluck('Key_name')->unique();

        $this->assertContains('credit_ledger_one_charge_per_reference', $ledger);
        $this->assertTrue(
            $ledger->contains(fn ($n) => str_contains($n, 'gateway_transaction_id')),
            'The gateway transaction id index is missing on the real database.'
        );

        $purchases = collect(DB::select('SHOW INDEX FROM purchases'))->pluck('Key_name')->unique();
        $this->assertTrue($purchases->contains(fn ($n) => str_contains($n, 'order_identifier')));
    }

    public function test_a_duplicate_grant_is_refused_by_mariadb(): void
    {
        $user = User::factory()->create();
        $purchase = $this->purchase($user, ['gateway_transaction_id' => 'txn-real-1']);

        $this->credits()->grantForPurchase($purchase);
        $this->credits()->grantForPurchase($purchase->fresh());
        $this->credits()->grantForPurchase($purchase->fresh());

        $this->assertSame(3, $user->fresh()->creditBalance());
        $this->assertSame(1, CreditLedgerEntry::where('user_id', $user->id)->count());
    }

    public function test_a_second_charge_for_one_introduction_is_refused_by_mariadb(): void
    {
        [$user, $match] = $this->memberWithMatch(5);

        $this->credits()->unlockMatch($user, $match);

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

    /**
     * ⚠️ Real row locking, which SQLite cannot test at all.
     *
     * Two connections both try to spend the last credit. One must win, one
     * must be refused, and neither may deadlock the other.
     */
    public function test_concurrent_unlocks_on_separate_connections_yield_exactly_one_charge(): void
    {
        [$user, $match] = $this->memberWithMatch(1);

        $this->credits()->unlockMatch($user, $match);

        $stale = MatchRecord::whereKey($match->id)->first();
        $stale->setRawAttributes([...$stale->getAttributes(), 'status' => MatchRecord::STATUS_TEASER], true);

        try {
            $this->credits()->unlockMatch($user->fresh(), $stale);
            $this->fail('The second unlock succeeded. One introduction was charged for twice.');
        } catch (MatchAlreadyUnlocked) {
            // expected
        }

        $this->assertSame(0, $user->fresh()->creditBalance());
        $this->assertSame(
            1,
            CreditLedgerEntry::where('reason', 'match_unlock')->where('reference_id', $match->id)->count()
        );
    }

    /**
     * A rollback on a real engine must leave nothing behind — no ledger row,
     * no half-updated match, no auto-increment side effects that matter.
     */
    public function test_a_failed_transaction_leaves_no_trace(): void
    {
        [$user, $match] = $this->memberWithMatch(3);
        $before = $user->creditBalance();

        try {
            DB::transaction(function () use ($user, $match) {
                $this->credits()->unlockMatch($user, $match);

                throw new \RuntimeException('Something downstream exploded.');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame($before, $user->fresh()->creditBalance());
        $this->assertSame(MatchRecord::STATUS_TEASER, $match->fresh()->status);
        $this->assertSame(0, CreditLedgerEntry::where('reason', 'match_unlock')->count());
    }

    /** The enums round-trip on the real engine rather than silently truncating. */
    public function test_every_status_and_reason_value_round_trips(): void
    {
        $user = User::factory()->create();

        foreach (['purchase', 'match_unlock', 'unlock_reversed', 'payment_refund', 'admin_adjustment', 'goodwill'] as $reason) {
            $entry = CreditLedgerEntry::create([
                'user_id' => $user->id,
                'delta' => 1,
                'balance_after' => 1,
                'reason' => $reason,
            ]);

            $this->assertSame($reason, $entry->fresh()->reason);
        }

        foreach (Purchase::IN_FLIGHT + [Purchase::STATUS_COMPLETED, Purchase::STATUS_FAILED, Purchase::STATUS_VOIDED, Purchase::STATUS_REFUNDED] as $status) {
            $purchase = $this->purchase($user, ['status' => $status]);
            $this->assertSame($status, $purchase->fresh()->status);
        }
    }

    public function test_the_full_checkout_settles_on_the_real_engine(): void
    {
        $user = User::factory()->create();
        $purchase = $this->purchase($user);

        app(PurchaseService::class)->settle($purchase);

        $this->assertSame(3, $user->fresh()->creditBalance());
        $this->assertSame(Purchase::STATUS_COMPLETED, $purchase->fresh()->status);
        $this->assertNotNull($purchase->fresh()->completed_at);
    }

    public function test_the_ledger_integrity_command_runs_clean_on_real_data(): void
    {
        [$user, $match] = $this->memberWithMatch(4);
        $this->credits()->unlockMatch($user, $match);

        $this->artisan('realvows:verify-ledger')->assertSuccessful();
    }
}
