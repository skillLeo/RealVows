<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which accounts are invented.
 *
 * ⚠️ A MARKER ON THE ROW, BECAUSE THE ALTERNATIVE IS A HEURISTIC AND A
 * HEURISTIC DELETES A REAL MEMBER ON LAUNCH DAY.
 *
 * The teardown has to remove thirty invented members and nothing else. Without
 * a marker the only way to identify them is a pattern — `@example.com`, or the
 * seeded names — and a pattern is exactly wrong for a destructive command:
 * a real member at `someone@example.com.jm`, or anyone who happens to share a
 * seeded first name, is inside the blast radius.
 *
 * The marker is set once, by the seeder that invents them, and nothing else
 * ever sets it. An account without it can never be deleted by the teardown, no
 * matter what it looks like.
 *
 * ⚠️ IT IS ALSO THE THING THE LAUNCH CHECKLIST COUNTS. "Are the demo members
 * gone?" has to be answerable with a query rather than by looking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('is_active');
            $table->index('is_demo');
        });

        /*
         * ⚠️ BACKFILL FOR THE ALREADY-SEEDED THIRTY.
         *
         * They exist on the review build with no marker, so without this the
         * teardown would find nothing and report success — the quiet-failure
         * shape this project keeps being bitten by.
         *
         * Matching on the seeded domain is a heuristic, which is the very
         * thing the marker exists to avoid. It is acceptable HERE and only
         * here: it runs once, against a database whose only accounts are
         * seeded ones and the developer's, and any account it misses simply
         * survives the teardown rather than being wrongly deleted. The safe
         * direction.
         */
        DB::table('users')
            ->where('email', 'like', '%@example.com')
            ->update(['is_demo' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_demo']);
            $table->dropColumn('is_demo');
        });
    }
};
