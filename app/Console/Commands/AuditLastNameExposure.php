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
 * ⚠️ `viewed_at` IS THE WRONG MARKER, AND THE FIRST VERSION OF THIS COMMAND
 * USED IT AND CONFIDENTLY REPORTED "NONE".
 *
 * `viewed_at` is stamped only by the match SHOW page. But the DASHBOARD
 * renders the same full() payload for every unlocked card and stamps nothing —
 * and unlock() redirects straight to the dashboard, so a member lands on a
 * rendered unlocked card the instant they pay.
 *
 * "Unlocked but never viewed" therefore does NOT mean "never rendered". It
 * means the member paid, was returned to a dashboard already showing the last
 * name, and never clicked through to the full page afterwards.
 *
 * Reporting "none" from that marker would have been this project's recurring
 * failure once more: a check answering confidently while measuring the wrong
 * thing. It is recorded here rather than quietly corrected.
 *
 * ⚠️ THE HONEST TEST IS SIMPLY: DID A NON-DEMO ACCOUNT EVER HOLD AN UNLOCKED
 * MATCH? If it did, the name was on screen.
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
         * ⚠️ NO `viewed_at` FILTER. See the note on this class: the dashboard
         * renders the full payload for every unlocked card and stamps nothing,
         * and unlocking redirects the member straight onto it. Holding an
         * unlocked match IS the exposure.
         */
        $exposed = MatchRecord::query()
            ->where('status', MatchRecord::STATUS_UNLOCKED)
            ->whereHas('seeker', fn ($q) => $q->whereNotIn('user_id', $demoEmails))
            ->with('seeker.user', 'matched')
            ->get();

        $this->line('  Unlocked matches        '.MatchRecord::where('status', MatchRecord::STATUS_UNLOCKED)->count());
        $this->line('  …held by demo accounts  '.(MatchRecord::where('status', MatchRecord::STATUS_UNLOCKED)->count() - $exposed->count()));
        $this->line('');

        // Shown for context: it is NOT the exposure test, only an indication
        // of how many also opened the full match page.
        $this->line('  (opened the full page   '.MatchRecord::where('status', MatchRecord::STATUS_UNLOCKED)->whereNotNull('viewed_at')->count().' — not the marker, see class note)');
        $this->line('');

        if ($exposed->isEmpty()) {
            $this->info('  RESULT: none.');
            $this->line('');
            $this->line('  No non-demo account has ever held an unlocked introduction, so no');
            $this->line('  real member was ever shown a last name by the defective payload.');
            $this->line('');

            return self::SUCCESS;
        }

        $this->error('  RESULT: '.$exposed->count().' unlocked introductions were held by non-demo accounts.');
        $this->line('');

        foreach ($exposed as $match) {
            $this->line(sprintf(
                '    match #%d  seeker user #%s <%s>  saw: %s  unlocked %s',
                $match->id,
                $match->seeker?->user_id ?? '?',
                $match->seeker?->user?->email ?? '?',
                trim(($match->matched?->first_name ?? '?').' '.($match->matched?->last_name ?? '?')),
                $match->unlocked_at?->toDateTimeString() ?? '?',
            ));
        }

        $this->line('');
        $this->warn('  Each seeker above was shown the matched member\'s last name before mutual');
        $this->warn('  interest. Judge the blast radius by WHO these accounts are: a developer or');
        $this->warn('  the client testing is not a disclosure; a real member is.');
        $this->line('');

        return self::FAILURE;
    }
}
