<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The free period, recorded rather than inferred.
 *
 * ⚠️ A FREE UNLOCK STILL WRITES A LEDGER ROW, AND THAT IS THE WHOLE POINT.
 *
 * The tempting shortcut is to skip the ledger entirely when nothing is being
 * charged — no money moved, so why record it? Because the ledger is not a
 * record of money, it is the record of WHY EVERY INTRODUCTION IS OPEN. Skip it
 * and an unlocked match during the free period has no explanation attached to
 * it at all, which means:
 *
 *   - `verify-ledger` can no longer reconcile: it finds unlocked matches with
 *     no corresponding entry and reports drift that is not drift.
 *   - Nobody can answer "how many introductions did we give away?" — the one
 *     number that decides whether the free period worked.
 *   - When payments switch on, there is no way to tell an introduction granted
 *     free from one somebody paid for, which is exactly the distinction the
 *     permanence guarantee depends on.
 *
 * So: a zero-delta entry with its own reason. Zero because no credit changed
 * hands, present because something happened.
 *
 * ⚠️ `free_period_granted_at` ON THE MATCH IS THE PERMANENCE GUARANTEE.
 *
 * Members who joined during the free period keep every introduction they
 * received when payments come on. That promise has to be enforceable from the
 * row itself — not from a date comparison against a settings value somebody
 * can change, and not from the ledger, which a future migration might archive.
 * A stamp on the match is the durable form.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL enums need rewriting; the string column avoids a second
        // migration the next time a reason is added.
        Schema::table('credit_ledger', function (Blueprint $table) {
            $table->string('reason', 32)->change();
        });

        Schema::table('matches', function (Blueprint $table) {
            /*
             * ⚠️ NULL MEANS "NOT GRANTED FREE", AND IT IS NEVER CLEARED.
             *
             * Once stamped it stays stamped, through the end of the free
             * period and every deploy after. Clearing it would retroactively
             * change the terms a member joined under, which is the specific
             * failure the whole design is built to prevent.
             */
            $table->timestamp('free_period_granted_at')->nullable()->after('unlocked_at');
            $table->index('free_period_granted_at');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex(['free_period_granted_at']);
            $table->dropColumn('free_period_granted_at');
        });

        Schema::table('credit_ledger', function (Blueprint $table) {
            $table->enum('reason', [
                'purchase', 'match_unlock', 'unlock_reversed',
                'payment_refund', 'admin_adjustment', 'goodwill',
            ])->change();
        });
    }
};
