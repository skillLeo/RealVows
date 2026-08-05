<?php

namespace App\Console\Commands;

use App\Models\CreditLedgerEntry;
use App\Models\MatchRecord;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\confirm;

/**
 * Remove the invented members before launch.
 *
 * ⚠️ THE GUARD MATTERS MORE THAN THE DELETION.
 *
 * Thirty invented members populate the platform so the client can walk through
 * a product that looks alive. They have to go before real members arrive — a
 * real person matched with an invented one is the worst first impression this
 * product could make.
 *
 * But a teardown is a destructive command run once, under time pressure, on
 * launch day, by somebody who wants it to be over. That is the worst possible
 * combination, so the design assumes the operator is tired and in a hurry:
 *
 *   - It REFUSES outright if any completed purchase would be touched. Money
 *     having changed hands is the definitive signal that an account is real,
 *     whatever its email address looks like.
 *   - It defaults to a dry run. `--execute` is required to delete anything.
 *   - It prints exactly what it will remove, and from where, before doing it.
 *
 * ⚠️ "DEMO" IS DECIDED BY A MARKER ON THE ROW, NOT BY A PATTERN.
 *
 * Matching on `@example.com` would be a heuristic, and a heuristic is how a
 * real member called `someone@example.com.jm` gets deleted on launch day. The
 * seeder stamps `is_demo`; nothing else is ever eligible.
 */
class TearDownDemoData extends Command
{
    protected $signature = 'realvows:teardown-demo
                            {--execute : Actually delete. Without this it is a dry run.}
                            {--force : Skip the confirmation prompt (for CI only)}
                            {--clear-demo-purchases : Also delete completed purchases belonging to demo accounts}';

    protected $description = 'Remove demo members, their photographs, introductions and ledger rows';

    public function handle(): int
    {
        /*
         * ⚠️ NEVER AN ADMINISTRATOR, WHATEVER THE MARKER SAYS.
         *
         * The seeder creates admin@ and reviewer@ on the same domain as the
         * invented members and stamps everything it makes, so a marker alone
         * would put the SUPER ADMIN inside the blast radius. Deleting it is
         * not "some test data went missing" — it is the client locked out of
         * his own product on launch day, recoverable only over SSH.
         *
         * This is the second of two independent guards. The other lives in the
         * backfill migration, which excludes privileged accounts from being
         * marked at all. Two, because the first is a data condition that
         * somebody could edit.
         */
        $privileged = \DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->pluck('model_id');

        $demoUserIds = User::query()
            ->where('is_demo', true)
            ->whereNotIn('id', $privileged)
            ->pluck('id');

        if ($demoUserIds->isEmpty()) {
            $this->info('No demo members found. Nothing to do.');

            return self::SUCCESS;
        }

        $profileIds = Profile::whereIn('user_id', $demoUserIds)->pluck('id');

        /*
         * ⚠️ THE REFUSAL. Checked before anything is counted, let alone
         * deleted, so a failure here costs nothing.
         *
         * A completed purchase against a supposedly-demo account means one of
         * two things, and both are reasons to stop: either a real member has
         * been marked demo, or a demo account was used for a live transaction.
         * Neither is something to resolve by deleting the evidence.
         */
        $paid = Purchase::whereIn('user_id', $demoUserIds)
            ->where('status', 'completed')
            ->count();

        if ($paid > 0 && ! $this->option('clear-demo-purchases')) {
            $this->error("REFUSING: {$paid} completed purchase(s) belong to accounts marked demo.");
            $this->line('');
            $this->line('  Money having changed hands is the definitive signal that an account is');
            $this->line('  real. Either a real member is flagged demo, or a demo account took a');
            $this->line('  live payment.');
            $this->line('');
            $this->line('  Look at them first:');
            $this->line('      php artisan realvows:redispatch-receipts');
            $this->line('');
            $this->line('  If they really are seeded test transactions, say so explicitly:');
            $this->line('      --clear-demo-purchases');
            $this->line('');

            return self::FAILURE;
        }

        /*
         * ⚠️ THE OVERRIDE IS A SEPARATE, TYPED FLAG — NOT A --force.
         *
         * The guard exists because the command cannot tell a seeded purchase
         * from a real one, and neither can anybody reading a summary at speed.
         * So the way past it is not "try harder", it is a flag whose name says
         * what it does, typed by somebody who has looked at the rows.
         *
         * ⚠️ IT DOES NOT SUPPRESS THE WARNING. Routing around a guard silently
         * is how a guard becomes decoration; the count is printed every time,
         * so an override never looks like a clean run.
         */
        if ($paid > 0) {
            $this->warn("  ⚠️  {$paid} completed purchase(s) will be deleted with these accounts.");
            $this->warn('  Overridden with --clear-demo-purchases. Money records are not');
            $this->warn('  recoverable after this, so be certain none of them is real.');
            $this->line('');
        }

        $counts = [
            'members' => $demoUserIds->count(),
            'profiles' => $profileIds->count(),
            'photographs' => Photo::whereIn('profile_id', $profileIds)->count(),
            'introductions' => MatchRecord::whereIn('seeker_profile_id', $profileIds)
                ->orWhereIn('matched_profile_id', $profileIds)->count(),
            'ledger rows' => CreditLedgerEntry::whereIn('user_id', $demoUserIds)->count(),
        ];

        $this->line('');
        $this->info($this->option('execute') ? '── Teardown ──' : '── Teardown (DRY RUN) ──');
        $this->line('');

        foreach ($counts as $what => $count) {
            $this->line(sprintf('  %-16s %d', $what, $count));
        }

        $this->line('');

        /*
         * ⚠️ INTRODUCTIONS ARE COUNTED IN BOTH DIRECTIONS, and that is worth
         * stating: a real member matched WITH a demo member has a row that
         * must also go, or they are left holding an introduction to somebody
         * who no longer exists.
         */
        $orphaned = MatchRecord::whereNotIn('seeker_profile_id', $profileIds)
            ->whereIn('matched_profile_id', $profileIds)
            ->count();

        if ($orphaned > 0) {
            $this->warn("  {$orphaned} introduction(s) belong to REAL members and point at demo");
            $this->warn('  members. They are removed too, or those members are left holding an');
            $this->warn('  introduction to somebody who no longer exists.');
            $this->line('');
        }

        if (! $this->option('execute')) {
            $this->line('  Nothing was changed. Re-run with --execute to delete.');
            $this->line('');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! confirm('Delete all of the above?', default: false)) {
            $this->info('Left alone.');

            return self::SUCCESS;
        }

        /*
         * ⚠️ PHOTOGRAPH FILES ARE COLLECTED BEFORE THE ROWS GO.
         *
         * Delete the rows first and the paths are gone with them, leaving
         * orphaned files on disk that nothing can ever identify — on a host
         * where storage sits inside the served directory.
         */
        $paths = Photo::whereIn('profile_id', $profileIds)->pluck('path')->all();

        /*
         * ⚠️ forceDelete, NOT delete. User uses SoftDeletes.
         *
         * A soft delete is exactly wrong here and would be the quiet-failure
         * shape all over again: the command reports success, the launch
         * checklist ticks "demo members removed", and thirty invented rows are
         * still sitting in the table — still counted by anything using
         * withTrashed(), still holding photographs on disk, and still
         * restorable by an accident.
         *
         * Soft deletes exist so a real member's data can be recovered after a
         * mistake. Nothing here was ever real, and the entire purpose of this
         * command is that it is gone.
         */
        DB::transaction(function () use ($demoUserIds, $profileIds) {
            MatchRecord::whereIn('seeker_profile_id', $profileIds)->forceDelete();
            MatchRecord::whereIn('matched_profile_id', $profileIds)->forceDelete();
            Photo::whereIn('profile_id', $profileIds)->forceDelete();
            CreditLedgerEntry::whereIn('user_id', $demoUserIds)->forceDelete();
            Purchase::whereIn('user_id', $demoUserIds)->forceDelete();
            Profile::whereIn('id', $profileIds)->forceDelete();
            User::whereIn('id', $demoUserIds)->forceDelete();
        });

        $removed = 0;

        foreach ($paths as $path) {
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
                $removed++;
            }
        }

        $this->line('');
        $this->info("Done. {$removed} photograph file(s) removed from disk.");
        $this->line('');

        return self::SUCCESS;
    }
}
