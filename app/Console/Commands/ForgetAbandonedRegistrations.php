<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Models\User;
use App\Services\Photos\PhotoStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ⚠️ EMAIL VERIFICATION MOVING OFF THE FRONT DOOR CREATED A CATEGORY OF DATA
 * THAT DID NOT EXIST BEFORE, AND NOTHING WAS GOING TO CLEAR IT.
 *
 * ── What changed ──────────────────────────────────────────────────────────
 *
 * Registration used to stop at the door. An account that never confirmed its
 * address held an email and a password hash, and that was all it could ever
 * hold, because the builder was behind the `verified` middleware.
 *
 * Now the builder is open. Somebody can register, upload four photographs of
 * their face, write about their marriage, enter their date of birth, and type
 * their father's name and mobile number — and then never confirm, and never
 * come back. That record sits there indefinitely: a real person's face, and a
 * third party's phone number given to us by somebody we never confirmed was
 * reachable at all.
 *
 * ⚠️ THE POLICY SENTENCE CAME SECOND, ON PURPOSE. Writing "we delete these
 * after twelve months" into a privacy policy with nothing scheduled to do it
 * is not a retention period, it is a sentence. This runs first so the document
 * describes something that happens.
 *
 * ── ⚠️ WHY THE DEFAULT IS A DRY RUN ───────────────────────────────────────
 *
 * Every other scheduled command here reports. This one destroys, and it
 * destroys the exact thing a member would be most upset to lose. A wrong date
 * comparison in a reporting command is a confusing morning; the same mistake
 * here is unrecoverable and silent. `--apply` is what the scheduler passes,
 * and a human running it by hand sees the list before anything happens.
 */
class ForgetAbandonedRegistrations extends Command
{
    protected $signature = 'realvows:forget-abandoned
                            {--apply : Actually delete. Without this it only reports.}
                            {--months=12 : The retention period stated in the privacy policy.}';

    protected $description = 'Delete registrations that never confirmed an email address.';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $cutoff = now()->subMonths($months);

        /*
         * ⚠️ THREE CONDITIONS, AND THE SECOND TWO ARE NOT REDUNDANT.
         *
         * `email_verified_at` null is the category. But an admin, a reviewer or
         * a staff account created by `realvows:create-admin` may also carry a
         * null timestamp, and deleting one of those would take a working
         * operator account out of the platform for a reason nobody could trace.
         *
         * A member who confirmed and later had the timestamp cleared is not
         * reachable by this either — the column is the only evidence, and being
         * wrong here costs somebody their profile.
         */
        $abandoned = User::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', $cutoff)
            ->whereHas('roles', fn ($q) => $q->where('name', 'member'))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'reviewer']))
            ->with('profile.photos')
            ->get();

        if ($abandoned->isEmpty()) {
            $this->info("Nothing to forget. No unconfirmed registration is older than {$months} months.");

            return self::SUCCESS;
        }

        $this->warn("{$abandoned->count()} registration(s) never confirmed an address and are older than {$months} months:");

        foreach ($abandoned as $user) {
            $photos = $user->profile?->photos->count() ?? 0;
            $this->line("  · #{$user->id}  registered {$user->created_at->diffForHumans()}  photographs: {$photos}");
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->info('Nothing was deleted. Pass --apply to carry it out.');

            return self::SUCCESS;
        }

        $files = 0;

        foreach ($abandoned as $user) {
            /*
             * ⚠️ THE FILES GO BEFORE THE ROWS, AND THE ORDER IS THE WHOLE
             * POINT.
             *
             * Deleting the row first leaves the photographs on disk with
             * nothing left pointing at them: a face on a server that no record
             * mentions, which no audit will ever find and no request to be
             * forgotten can reach. The privacy policy says we delete the files
             * themselves rather than only the record pointing at them, and this
             * is the line that makes that true.
             */
            foreach ($user->profile?->photos ?? [] as $photo) {
                $files += $this->forgetFilesOf($photo);
            }
        }

        $ids = $abandoned->pluck('id')->all();

        /*
         * ⚠️ forceDelete, AND THE ORDINARY delete() HERE WOULD HAVE BEEN A LIE
         * IN A LEGAL DOCUMENT.
         *
         * User soft-deletes. `delete()` sets `deleted_at` and leaves the row
         * exactly where it was — the date of birth, the address, and through
         * the cascade the trusted contact's mobile number — all still in the
         * database, indefinitely, under a policy sentence that says we deleted
         * it. The account would disappear from every screen, which is precisely
         * what makes the mistake impossible to notice.
         *
         * ⚠️ THE CASCADE ONLY FIRES ON A REAL DELETE. `profiles.user_id` and
         * `guardians.member_id` are both cascadeOnDelete, so the profile, the
         * photograph rows and the trusted contact go with the user here and
         * would all have survived a soft delete.
         */
        DB::transaction(fn () => User::whereIn('id', $ids)->forceDelete());

        /*
         * ⚠️ RECORDED WHERE SOMEBODY WILL SEE IT. A destructive scheduled task
         * whose only trace is the absence of rows is how a bug in the date
         * comparison runs for months before anybody notices.
         */
        Log::info('Abandoned registrations forgotten.', [
            'users' => $ids,
            'files_deleted' => $files,
            'retention_months' => $months,
            'why' => 'Never confirmed an email address. Stated in the privacy policy.',
        ]);

        $this->newLine();
        $this->info(count($ids).' registration(s) and '.$files.' file(s) deleted.');

        return self::SUCCESS;
    }

    /** Every derivative, not only the original. */
    private function forgetFilesOf(Photo $photo): int
    {
        $disk = Storage::disk(PhotoStorageService::DISK);
        $deleted = 0;

        foreach ([$photo->path, $photo->webp_path, $photo->thumbnail_path] as $path) {
            if ($path && $disk->exists($path)) {
                $disk->delete($path);
                $deleted++;
            }
        }

        return $deleted;
    }
}
