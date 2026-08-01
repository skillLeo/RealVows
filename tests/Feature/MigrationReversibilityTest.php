<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ⚠️ A `down()` that has been written but never run is not reversible, it is
 * only optimistic.
 *
 * This one was wrong until it was tested. The obvious version dropped the
 * three tables and stopped — but those tables were created by PHASE 1
 * migrations, which stay marked as run during a rollback. The database was
 * left claiming three tables existed that did not, and the only reason it
 * appeared to recover was that re-running the reshape rebuilt them.
 *
 * Rolling back and stopping left a broken database, and nothing said so.
 */
class MigrationReversibilityTest extends TestCase
{
    private const PHASE_2_STEPS = 4;

    private const RESHAPED = ['matches', 'credit_ledger', 'purchases'];

    private const CREATED = ['payment_events', 'notification_preferences'];

    /**
     * ⚠️ Rolls the schema back and forward on the test connection. Safe
     * because the test database is in-memory and thrown away, but never point
     * this at anything else.
     */
    public function test_rolling_back_phase_2_restores_the_phase_1_schema(): void
    {
        Artisan::call('migrate:fresh');

        // Phase 2 shape, as deployed.
        $this->assertTrue(Schema::hasColumn('matches', 'unlocked_by_ledger_id'));
        $this->assertTrue(Schema::hasColumn('purchases', 'order_identifier'));
        $this->assertTrue(Schema::hasTable('payment_events'));

        Artisan::call('migrate:rollback', ['--step' => self::PHASE_2_STEPS]);

        /*
         * The load-bearing assertion. Rollback must leave the three tables
         * PRESENT in their Phase 1 shape — not dropped — because the Phase 1
         * migrations that created them are still marked as run.
         */
        foreach (self::RESHAPED as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "[{$table}] was dropped by the rollback, leaving its Phase 1 migration "
                    .'marked as run against a table that does not exist.'
            );
        }

        // Back to the Phase 1 columns, exactly.
        $this->assertTrue(Schema::hasColumn('matches', 'is_unlocked'), 'The Phase 1 boolean was not restored.');
        $this->assertFalse(Schema::hasColumn('matches', 'unlocked_by_ledger_id'));
        $this->assertFalse(Schema::hasColumn('matches', 'commonalities'));
        $this->assertFalse(Schema::hasColumn('purchases', 'order_identifier'));
        $this->assertFalse(Schema::hasColumn('purchases', 'spi_token'));

        // Purely additive migrations do drop their tables, which is correct —
        // nothing created those before Phase 2.
        foreach (self::CREATED as $table) {
            $this->assertFalse(Schema::hasTable($table), "[{$table}] should not survive its own rollback.");
        }

        $this->assertFalse(Schema::hasColumn('profiles', 'matching_fields_hash'));
        $this->assertFalse(Schema::hasColumn('profiles', 'body_type_is_dealbreaker'));
    }

    /** And forward again, so the cycle is genuinely repeatable. */
    public function test_the_schema_survives_a_full_down_and_up_cycle(): void
    {
        Artisan::call('migrate:fresh');
        Artisan::call('migrate:rollback', ['--step' => self::PHASE_2_STEPS]);
        Artisan::call('migrate');

        foreach ([...self::RESHAPED, ...self::CREATED] as $table) {
            $this->assertTrue(Schema::hasTable($table), "[{$table}] did not come back.");
        }

        $this->assertTrue(Schema::hasColumn('matches', 'unlocked_by_ledger_id'));
        $this->assertTrue(Schema::hasColumn('matches', 'commonalities'));
        $this->assertFalse(Schema::hasColumn('matches', 'is_unlocked'), 'The Phase 1 boolean came back with it.');
        $this->assertTrue(Schema::hasColumn('purchases', 'order_identifier'));
        $this->assertTrue(Schema::hasColumn('profiles', 'matching_fields_hash'));
    }

    /**
     * The migrations table and the actual schema must agree. A migration
     * marked as run against a table that does not exist is the exact
     * inconsistency this file was written to catch.
     */
    public function test_no_migration_is_marked_as_run_against_a_missing_table(): void
    {
        Artisan::call('migrate:fresh');
        Artisan::call('migrate:rollback', ['--step' => self::PHASE_2_STEPS]);

        $ran = \DB::table('migrations')->pluck('migration');

        $createdByPhase1 = [
            '010700' => 'credit_ledger',
            '010800' => 'purchases',
            '010900' => 'matches',
        ];

        foreach ($createdByPhase1 as $stamp => $table) {
            $isMarkedRun = $ran->contains(fn ($m) => str_contains($m, $stamp));

            if ($isMarkedRun) {
                $this->assertTrue(
                    Schema::hasTable($table),
                    "The migration creating [{$table}] is marked as run, but the table is gone."
                );
            }
        }

        Artisan::call('migrate');
    }
}
