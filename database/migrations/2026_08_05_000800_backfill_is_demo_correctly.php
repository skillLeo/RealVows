<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ⚠️ THE FIRST BACKFILL MATCHED THE WRONG DOMAIN AND MARKED NOTHING.
 *
 * `2026_08_05_000700` backfilled `is_demo` for `%@example.com`. The seeder uses
 * `DemoSeeder::DOMAIN`, which is `realvows.test`. So on production not one of
 * the thirty-two invented accounts carried the marker, and the teardown
 * reported:
 *
 *     "No demo members found. Nothing to do."
 *
 * Success, while deleting nothing — the ninth instance of this project's
 * recurring failure, and it would have been discovered on launch day when the
 * demo members were still visible to real members after the box was ticked.
 *
 * It was caught by the dry run, which is the entire argument for dry runs.
 *
 * ⚠️ THE PATTERN IS READ FROM THE SEEDER'S OWN CONSTANT, so it cannot drift
 * from the thing it describes a second time.
 *
 * ── ⚠️ ADMINISTRATORS ARE EXCLUDED, AND THIS IS THE SERIOUS PART ──────────
 *
 * The seeder creates `admin@realvows.test` and `reviewer@realvows.test` on the
 * same domain as the invented members, and stamps every account it makes. A
 * naive backfill therefore marks the SUPER ADMIN as demo — and the teardown
 * would delete it.
 *
 * That is not "some test data went missing". It is the client locked out of
 * his own product, on launch day, with no way back in except an SSH session
 * and `realvows:create-admin`. Nothing else in this teardown is irreversible
 * in that way.
 *
 * So admins are excluded here, and TearDownDemoData refuses to delete a
 * privileged account regardless of its marker. Two independent guards, because
 * one of them is a data condition somebody could edit.
 */
return new class extends Migration
{
    public function up(): void
    {
        $domain = \Database\Seeders\DemoSeeder::demoDomain();

        /*
         * Every account holding any role. spatie's pivot is the source of
         * truth for privilege, so it is what the exclusion reads.
         */
        $privileged = DB::table('model_has_roles')
            ->where('model_type', \App\Models\User::class)
            ->pluck('model_id');

        DB::table('users')
            ->where('email', 'like', '%@'.$domain)
            ->whereNotIn('id', $privileged)
            ->update(['is_demo' => true]);

        /*
         * ⚠️ AND UNMARK ANY PRIVILEGED ACCOUNT THAT THE SEEDER ALREADY
         * STAMPED. Without this, a fresh seed on a machine that has run the
         * newer seeder leaves an administrator marked for deletion.
         */
        DB::table('users')
            ->whereIn('id', $privileged)
            ->update(['is_demo' => false]);
    }

    public function down(): void
    {
        // The column's own migration owns dropping it; this only set values.
    }
};
