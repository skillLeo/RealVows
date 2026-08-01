<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ⚠️ THIS MIGRATION DROPS THREE TABLES AND REBUILDS THEM. Read before
     * copying its approach anywhere else.
     *
     * It is safe exactly once, and this is that once. The three tables were
     * created in Phase 1 so their constraints would be right while they were
     * still empty. They now hold nothing but demonstration rows written by
     * DemoSeeder — invented people, invented purchases — with no value to
     * anyone. DemoSeeder rebuilds them by running the real engine.
     *
     * The alternative was a long chain of ALTERs. Rejected because the enum
     * changes behave differently on the three engines this codebase runs on
     * (SQLite for tests, MariaDB 10.4 locally, MariaDB 11.8 live), and a
     * migration that is subtly different per engine is worse than one that
     * is obviously destructive.
     *
     * ⚠️ THE MOMENT A REAL PAYMENT SETTLES, THIS APPROACH BECOMES ILLEGAL.
     * `credit_ledger` is the record of money members have paid. From launch,
     * every change to these tables is an additive ALTER with a backfill, and
     * never a drop.
     */
    public function up(): void
    {
        Schema::dropIfExists('matches');
        Schema::dropIfExists('credit_ledger');
        Schema::dropIfExists('purchases');

        $this->createPurchases();
        $this->createCreditLedger();
        $this->createMatches();
    }

    private function createPurchases(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /*
             * Price and credit count are SNAPSHOTTED here at the moment of
             * purchase, never read back from settings. If the client changes
             * a price next month, a receipt from today must still show what
             * was actually paid.
             */
            $table->enum('package_key', ['single', 'triple', 'octet']);
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->unsignedSmallInteger('credits_granted');

            $table->enum('status', [
                'initiated',   // created, nothing sent to the gateway yet
                'pending_3ds', // handed to the issuing bank, awaiting the challenge
                'authorised',  // bank approved, not yet captured/credited
                'completed',   // credits granted — the only status that grants
                'failed',
                'voided',
                'refunded',
            ])->default('initiated');

            $table->string('gateway', 32)->default('powertranz');

            /*
             * Sandbox versus live is CONFIGURATION, never a code path — but
             * it is recorded per row, because "was this a real transaction?"
             * must be answerable from the data alone a year later.
             */
            $table->enum('gateway_mode', ['sandbox', 'live'])->default('sandbox');

            /*
             * ⚠️ LOAD-BEARING. Ours, a UUID, generated before anything is
             * sent. The 3DS return is a cross-site POST that arrives with no
             * session cookie, so this is how the callback finds the purchase
             * without being able to ask "who is logged in?".
             */
            $table->uuid('order_identifier')->unique();

            // The whole idempotency guarantee on the payment side.
            $table->string('gateway_transaction_id')->nullable()->unique();

            $table->text('spi_token')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message')->nullable();

            // Redacted before it is written. Never any card data.
            $table->json('raw_response')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']); // ReconcilePendingPayments
        });
    }

    private function createCreditLedger(): void
    {
        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->integer('delta'); // +8 on purchase, −1 on unlock

            /*
             * NOT the source of truth. User::creditBalance() sums `delta`,
             * and that is the only authority — a cached balance drifts, a
             * derived one cannot.
             *
             * This column is written inside the same locked transaction as
             * the insert and exists for two reasons: a statement can show a
             * running balance without a window function, and the nightly
             * integrity job can walk the chain and prove no row was ever
             * altered behind the application's back.
             */
            $table->integer('balance_after');

            $table->enum('reason', [
                'purchase',
                'match_unlock',
                'unlock_reversed',
                'payment_refund',
                'admin_adjustment',
                'goodwill',
            ]);

            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            // Required at validation level for admin_adjustment and goodwill.
            $table->text('note')->nullable();

            /*
             * ⚠️ THE IDEMPOTENCY GUARANTEE, AND IT IS THE CONSTRAINT THAT
             * PROVIDES IT — not the code around it, and not the row lock.
             *
             * A retried gateway callback is normal, not exceptional. This
             * unique index makes a double-credit impossible at the database
             * level, so the guarantee holds even where lockForUpdate() is a
             * no-op (SQLite) or a transaction is retried.
             */
            $table->string('gateway_transaction_id')->nullable()->unique();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);

            /*
             * ⚠️ THE SAME GUARANTEE ON THE SPENDING SIDE.
             *
             * A match can only be paid for once. Two tabs clicking Unlock at
             * the same moment both try to write this row; the second hits an
             * integrity violation and is caught, rather than quietly
             * producing a lost update and a free match.
             *
             * `reason` is part of the key so that a later unlock_reversed or
             * an admin adjustment referencing the same match is still
             * permitted — it is "one unlock per match", not "one row per
             * match".
             */
            $table->unique(
                ['reason', 'reference_type', 'reference_id'],
                'credit_ledger_one_charge_per_reference'
            );
        });
    }

    private function createMatches(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();

            /*
             * Directional, and keyed on PROFILES rather than users. Every
             * scorer reads profile fields, so keying on users would mean a
             * join on every row of every scoring pass to reach the data the
             * engine actually needs.
             *
             * A being shown B is one row; B being shown A is a separate row
             * with its own score, because preferences are not symmetric.
             */
            $table->foreignId('seeker_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('matched_profile_id')->constrained('profiles')->cascadeOnDelete();

            $table->decimal('total_score', 5, 2);

            /*
             * ⚠️ SERVER-SIDE ONLY. Never serialised to a member, in any
             * shape, on any route. It is visible on /admin/matches and
             * nowhere else. MatchRecord::$hidden is the second net; the
             * first is that no member-facing serialiser reads it.
             */
            $table->json('score_breakdown');

            // Generated from what the two people typed about themselves.
            $table->text('compatibility_rationale')->nullable();
            $table->json('commonalities')->nullable();

            /*
             * ⚠️ THREE INDEPENDENT AXES. Do not collapse them again.
             *
             * The Phase 1 shape crammed access, delivery and reaction into
             * one enum plus a boolean, and the two could disagree. A match
             * can be unlocked AND delivered AND viewed at the same instant,
             * which a single enum cannot express.
             *
             *   status   — access. Money touches only this.
             *   response — the member's reaction. Phase 3.
             *   the timestamps — delivery and attention, which are facts
             *                    about when, not states to be in.
             */
            $table->enum('status', ['teaser', 'unlocked', 'expired'])->default('teaser');
            $table->enum('response', ['interested', 'passed'])->nullable();

            $table->foreignId('unlocked_by_ledger_id')
                ->nullable()
                ->constrained('credit_ledger')
                ->nullOnDelete();

            $table->timestamp('generated_at')->useCurrent();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->unique(['seeker_profile_id', 'matched_profile_id']);
            $table->index(['seeker_profile_id', 'status', 'total_score']);
            $table->index(['matched_profile_id', 'response']); // "no prior pass either way"
        });
    }

    /**
     * ⚠️ REVERSIBILITY, AND WHY THIS IS LONGER THAN YOU EXPECT.
     *
     * The obvious `down()` — three dropIfExists calls — is wrong, and it was
     * wrong here until it was actually tested rather than assumed.
     *
     * These tables were created by three PHASE 1 migrations, which stay
     * marked as run when this one is rolled back. Dropping without recreating
     * leaves the database claiming three tables exist that do not, and the
     * only reason it appears to recover is that re-running this migration
     * builds them again. Rolling back and stopping leaves a broken database.
     *
     * So `down()` restores the Phase 1 shapes exactly. Rollback returns the
     * schema to what the previous migration left behind, which is the whole
     * contract.
     *
     * Data is not restored, and cannot be — see the warning on `up()`.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
        Schema::dropIfExists('credit_ledger');
        Schema::dropIfExists('purchases');

        // ── The Phase 1 shapes, restored verbatim ────────────────────────

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('package_key', ['single', 'triple', 'octet']);
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->unsignedSmallInteger('credits_granted');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('gateway', 32)->nullable();
            $table->string('gateway_transaction_id')->nullable()->unique();
            $table->json('gateway_response')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('delta');
            $table->integer('balance_after');
            $table->enum('reason', ['purchase', 'match_reserved', 'match_refunded', 'admin_adjustment']);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('gateway_transaction_id')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seeker_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('matched_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->decimal('total_score', 5, 2);
            $table->json('score_breakdown');
            $table->text('compatibility_rationale')->nullable();
            $table->enum('status', ['reserved', 'delivered', 'viewed', 'interested', 'passed', 'expired'])
                ->default('reserved');
            $table->boolean('is_unlocked')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['seeker_profile_id', 'matched_profile_id']);
            $table->index(['seeker_profile_id', 'status']);
            $table->index(['seeker_profile_id', 'is_unlocked']);
            $table->index('total_score');
        });
    }
};
