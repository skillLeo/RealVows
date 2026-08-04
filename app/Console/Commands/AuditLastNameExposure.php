<?php

namespace App\Console\Commands;

use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Who, if anyone, could actually have seen a last name before mutual interest?
 *
 * ⚠️ THIS ANSWERS FROM THE DATA, NOT FROM REASONING ABOUT THE CODE.
 *
 * The defect: MatchPresenter::full() returned `lastName` on any unlocked
 * introduction. The schema has said "private until mutual interest" since the
 * Phase 2 reshape; the presenter never caught up.
 *
 * "No real members exist yet, so nobody was exposed" is almost certainly true
 * and is exactly the kind of true-by-luck statement this project has been
 * caught by before — `.env` survived a deploy only because LiteSpeed blocks
 * dotfiles by default. Believing a thing and having checked it are different,
 * and only one of them is reportable to a client.
 *
 * ⚠️ THE TEST IS "DID AN UNLOCKED CARD EVER LOAD FOR A NON-DEMO ACCOUNT?"
 *
 * `viewed_at` is the honest marker: it is stamped the first time the full
 * match page is opened, which is the only surface that carried the payload.
 * An unlocked-but-never-viewed match means the card was never rendered and
 * therefore no name was ever sent to a browser.
 */
class AuditLastNameExposure extends Command
{
    protected $signature = 'realvows:audit-lastname-exposure';

    protected $description = 'Report whether any non-demo account ever loaded an unlocked introduction';

    public function handle(): int
    {
        $demoEmails = User::query()
            ->where('email', 'like', '%@example.com')
            ->orWhere('email', 'like', '%@realvows.test')
            ->pluck('id');

        $this->line('');
        $this->info('── Last-name exposure audit ──────────────────────────────');
        $this->line('');

        $this->line('  Accounts total          '.User::count());
        $this->line('  Accounts demo-shaped    '.$demoEmails->count());
        $this->line('  Profiles                '.Profile::count());
        $this->line('  Completed purchases     '.Purchase::where('status', 'completed')->count());
        $this->line('');

        /*
         * The population that matters: an unlocked match whose SEEKER is not a
         * demo account, that was actually opened.
         */
        $exposed = MatchRecord::query()
            ->where('status', MatchRecord::STATUS_UNLOCKED)
            ->whereNotNull('viewed_at')
            ->whereHas('seeker', fn ($q) => $q->whereNotIn('user_id', $demoEmails))
            ->with('seeker.user')
            ->get();

        $unlockedNeverViewed = MatchRecord::query()
            ->where('status', MatchRecord::STATUS_UNLOCKED)
            ->whereNull('viewed_at')
            ->count();

        $this->line('  Unlocked matches        '.MatchRecord::where('status', MatchRecord::STATUS_UNLOCKED)->count());
        $this->line('  …never opened           '.$unlockedNeverViewed.'  (payload never rendered)');
        $this->line('');

        if ($exposed->isEmpty()) {
            $this->info('  RESULT: none.');
            $this->line('');
            $this->line('  No non-demo account has ever opened an unlocked introduction, so no');
            $this->line('  real member ever received a last name from the defective payload.');
            $this->line('');

            return self::SUCCESS;
        }

        $this->error('  RESULT: '.$exposed->count().' unlocked introductions were opened by non-demo accounts.');
        $this->line('');

        foreach ($exposed as $match) {
            $this->line(sprintf(
                '    match #%d  seeker user #%s  opened %s',
                $match->id,
                $match->seeker?->user_id ?? '?',
                $match->viewed_at?->toDateTimeString() ?? '?',
            ));
        }

        $this->line('');
        $this->warn('  Each of these members was shown another member\'s last name before');
        $this->warn('  mutual interest. Both sides of each pair need to be identified.');
        $this->line('');

        return self::FAILURE;
    }
}
