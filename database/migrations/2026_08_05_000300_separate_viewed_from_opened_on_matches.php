<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ⚠️ THE ADMIN MATCH LOG HAS BEEN UNDER-REPORTING VIEWS, AND THIS FIXES BOTH
 * THE COLUMN AND THE HISTORY.
 *
 * `viewed_at` was stamped only by the match SHOW page. But the DASHBOARD
 * renders the same full() payload for every unlocked card — name, photographs,
 * biography, the lot — and stamped nothing. And `unlock()` redirects straight
 * to the dashboard, so a member is looking at the unlocked contents the instant
 * they pay.
 *
 * So a match could be seen, in full, and the admin log would report it as never
 * viewed. That is not merely an inconvenient metric: it is the screen the
 * client uses to understand whether the product is working, quietly telling him
 * members are ignoring introductions they have in fact read.
 *
 * ── TWO QUESTIONS WERE SHARING ONE COLUMN ─────────────────────────────────
 *
 *   viewed_at  — has the member SEEN the unlocked contents, anywhere?
 *   opened_at  — have they opened the full introduction PAGE?
 *
 * The second drives the arrival ceremony on the match page, which must run
 * exactly once. The first is what the admin log means by "Viewed". Conflating
 * them meant fixing one would silently break the other: stamping on the
 * dashboard would have made the ring never animate.
 *
 * ── ⚠️ THE BACKFILL IS AN INFERENCE. DO NOT "CORRECT" IT TO NULL ──────────
 *
 * Historic `viewed_at` values meant "opened the show page", so they move to
 * `opened_at` unchanged — that is a rename, not a guess.
 *
 * For `viewed_at` itself: every unlocked match WAS displayed in full, because
 * unlocking redirects onto a dashboard that renders it. So an unlocked row with
 * no view timestamp is not a member who never looked, it is a member we failed
 * to record. `unlocked_at` is the earliest moment they can have seen it, and is
 * therefore the honest value.
 *
 * ⚠️ This WILL look like fabricated data to somebody reading it later, and the
 * instinct will be to null it back out on the grounds that we cannot prove a
 * member looked. Resist that. It is not a guess about behaviour, it is a fact
 * about a CODE PATH: unlock() redirects to a dashboard that renders the match
 * in full, so there is no route by which an unlocked match went unseen.
 *
 * Leaving these null does not make the log neutral. It makes it assert the
 * opposite — "never viewed" — which is the exact lie this migration exists to
 * end, preserved under the appearance of caution.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('viewed_at');
        });

        // A rename, not a guess: this is exactly what viewed_at recorded.
        DB::table('matches')->whereNotNull('viewed_at')->update([
            'opened_at' => DB::raw('viewed_at'),
        ]);

        /*
         * ⚠️ Every unlocked match was rendered in full at least once, because
         * unlocking lands the member on a dashboard that shows it. The absence
         * of a timestamp recorded our omission, never their behaviour.
         */
        DB::table('matches')
            ->where('status', 'unlocked')
            ->whereNull('viewed_at')
            ->whereNotNull('unlocked_at')
            ->update(['viewed_at' => DB::raw('unlocked_at')]);
    }

    public function down(): void
    {
        /*
         * ⚠️ Restores viewed_at to what it meant before — the show-page stamp —
         * so rolling back does not leave the old code reading dashboard views
         * as page opens and replaying the ceremony for everybody.
         */
        DB::table('matches')->update(['viewed_at' => DB::raw('opened_at')]);

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('opened_at');
        });
    }
};
