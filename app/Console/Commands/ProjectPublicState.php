<?php

namespace App\Console\Commands;

use App\Support\FreePeriod;
use App\Support\PublicState;
use Illuminate\Console\Command;

/**
 * Rewrite the projection the public pages read.
 *
 * ⚠️ RUN FROM THE SCHEDULER AS WELL AS ON SAVE, and that is not belt and
 * braces — it is the only thing that makes the projection trustworthy.
 *
 * A projection written only when somebody presses Save is correct until the
 * one time the write fails: a full disk, a permissions change after a deploy,
 * a settings row edited straight in the database because the admin screen was
 * broken. After that it is silently stale, and a stale projection is worse
 * than no projection because it looks authoritative.
 *
 * Running it every minute means the worst case is a minute of wrong, not
 * forever of wrong. The cost is a file write on a host that already runs cron
 * every minute for the queue.
 */
class ProjectPublicState extends Command
{
    protected $signature = 'realvows:project-public-state {--quiet-if-unchanged}';

    protected $description = 'Write the free-period projection the public pages read';

    public function handle(FreePeriod $freePeriod, \App\Support\FoundingOffer $founding): int
    {
        /*
         * ⚠️ THE DATABASE READS HAPPEN HERE, IN A CONSOLE PROCESS, where a
         * failure costs a log line rather than a member's home page.
         */
        $active = $freePeriod->isActive();
        $placesLeft = $founding->remaining();

        if (! PublicState::write($active, $placesLeft)) {
            $this->error('Could not write '.PublicState::path().'.');
            $this->warn('The public pages will hide the pricing link until this succeeds.');

            return self::FAILURE;
        }

        if (! $this->option('quiet-if-unchanged')) {
            $this->info(sprintf(
                'Public state written. Free period %s, pricing link %s. Founding places left: %d.',
                $active ? 'ON' : 'OFF',
                $active ? 'hidden' : 'shown',
                $placesLeft,
            ));
        }

        return self::SUCCESS;
    }
}
