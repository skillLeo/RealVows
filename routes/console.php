<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| RealVows deploys to Hostinger shared hosting: no Supervisor, no root, no
| persistent processes. A queue worker therefore cannot run as a daemon.
|
| Instead a single cron entry on the server runs `php artisan schedule:run`
| every minute, and the schedule below drains whatever is waiting and exits.
| `withoutOverlapping()` stops a second worker starting on top of a running
| one, and `--max-time=50` guarantees it is finished before the next tick.
|
| Consequence for the product: background work is batched roughly every
| minute, never instant. UI copy must say "shortly" or "usually within a
| day" — never "instantly".
|
| Server cron line:
|   * * * * * cd /path/to/realvows && php artisan schedule:run >> storage/logs/scheduler.log 2>&1
|
| ⚠️ NOT `>> /dev/null`, AND IT WAS FOR MONTHS.
|
| Every byte the scheduler produced was discarded on the live server. If
| `schedule:run` itself failed — a PHP fatal, an unreadable .env, a database
| that would not answer — nothing recorded it and the system looked healthy.
| The one process responsible for running every other check was the only one
| reporting to nobody.
|
| ⚠️ AND THE OUTER REDIRECT IS ONLY HALF OF IT. Laravel sends each scheduled
| command's own output to /dev/null unless told otherwise, so fixing the cron
| line alone bought a log that says "DONE" and never says what was found. The
| commands that report findings therefore call `appendOutputTo` below.
|
| The rule this file now follows: a command that can DISCOVER something keeps
| its output. A command that only does work — draining a queue, rewriting a
| projection every minute — does not, because a log nobody can skim is the
| same void with more disk usage.
|
*/

/*
 * ⚠️ Output goes to storage/logs/scheduler.log — the same file the cron line
 * appends to, so one `tail` shows both what ran and what it said.
 *
 * Written out at each call rather than held in a file-scope `const`. This file
 * is loaded once per test by the framework, and a top-level const redefines on
 * the second load: 761 tests errored with "Constant SCHEDULER_LOG already
 * defined" before the repetition was accepted as the cheaper option.
 */

Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

/*
 * ⚠️ THE SITE MUST NOT BE ABLE TO STAY DOWN BECAUSE A DEPLOY DIED.
 *
 * The deploy takes the site down over SSH and brings it back over the same SSH
 * session. The bring-back is marked `always()`, which looks like a safety net
 * and is not one: if SSH is what failed, the repair fails for the same reason
 * as the damage. On 4 August 2026 a deploy died on `ssh-keyscan` and the site
 * survived only because the failure landed BEFORE `artisan down`.
 *
 * This runs on the server, from cron, over no network — so it cannot be taken
 * out by the thing it exists to repair. `artisan down` gates HTTP and not the
 * scheduler, so the one channel still working during an outage is this one.
 *
 * Every minute, because the cost of checking is a file_exists and the cost of
 * not checking is a client-facing outage nobody is watching for at 3am.
 */
Schedule::command('realvows:maintenance-watchdog')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

/*
 * ⚠️ KEEPS THE PUBLIC PAGES OUT OF THE DATABASE.
 *
 * The marketing pages need one fact — is the free period on — to decide
 * whether a Pricing link would point at a 404. Reading it per request would
 * put a query on the one page that otherwise touches no database, on shared
 * hosting, and that exact shape has already turned the home page into a 500
 * once. Caching it changes nothing here, because CACHE_STORE is `database`.
 *
 * So it is projected to a file, and rewritten here every minute. The admin
 * screen writes it on save; this is what makes a failed write self-repair
 * instead of leaving a projection that is confidently out of date.
 */
Schedule::command('realvows:project-public-state --quiet-if-unchanged')
    ->everyMinute()
    ->withoutOverlapping();

/*
 * ⚠️ DAILY, NOT EVERY MINUTE. A reminder is a message to a real person who did
 * somebody a favour by being named at all, and the command stamps each one so a
 * guardian on day 8 is not emailed every day until they act.
 */
Schedule::command('realvows:remind-trusted-contacts')
    ->dailyAt('09:20')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

/*
 * Tops up introductions for anyone verified whose set has gone stale. Queues
 * jobs rather than doing the work, so the drain above does it a chunk at a
 * time and being killed mid-run costs at most one chunk.
 */
Schedule::command('realvows:refresh-matches')
    ->dailyAt('03:10')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

/*
 * Finds purchases the gateway never told us the outcome of. This is what
 * saves a member who paid and never got their credits because a callback was
 * lost — without it, the only way that surfaces is a complaint.
 */
Schedule::command('realvows:reconcile-payments')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

/*
 * Walks every credit chain and proves nobody's balance drifted. Reports
 * rather than repairs — an automatic fix to a money record would destroy the
 * evidence of whatever caused the problem.
 */
Schedule::command('realvows:verify-ledger')
    ->dailyAt('03:40')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

/*
 * ⚠️ PROVES THE FILES WE PROMISE ACTUALLY EXIST.
 *
 * Twenty-two guardian records pointed at a joint photograph that had never
 * been written, and that photograph is the primary check in the whole guardian
 * system — a reviewer approves a trusted contact by looking at it. Every one of
 * those reviews threw, on the only screen the check happens on.
 *
 * The seeder reported success the whole time, because a path in a column looks
 * exactly like a photograph until somebody opens it. It was found by reading a
 * production log by hand, which is not a strategy.
 *
 * ⚠️ IN THE SCHEDULE RATHER THAN THE CRONTAB, so it is in version control and
 * travels with a deploy. A check that only exists on one server is one rebuild
 * away from never running again.
 */
Schedule::command('realvows:check-stored-files')
    ->dailyAt('04:17')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

/*
 * ⚠️ THE ALERTS THAT HAD NO LISTENER.
 *
 * Failed jobs, error-level log lines and undelivered messages were all being
 * detected correctly and consumed by nobody. `failed_jobs` is the sharp one: a
 * job that fails three times stops, and on this host that includes outbound
 * email, so the failure mode is a member waiting for an introduction the
 * platform believes it sent.
 *
 * ⚠️ MORNING, NOT NIGHT. Whoever reads this should be awake when it arrives.
 * An alert delivered at 03:40 is read at 09:00 anyway, having lost five hours
 * for no reason.
 *
 * It sends nothing when there is nothing to say, so its arrival is itself the
 * signal. See ReportSilentFailures for why that matters more than it sounds.
 */
Schedule::command('realvows:report-silent-failures')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

/*
 * ⚠️ TEMPLATE DRIFT IS SILENT AND EXPENSIVE, SO IT IS ASKED WEEKLY RATHER THAN
 * REMEMBERED.
 *
 * The templates were compared against Meta by hand, once. Editing an approved
 * template invalidates its approval, and a template that stops matching what
 * this code sends fails at the moment of sending — which on this platform means
 * a member never hearing about an introduction, with the failure landing in
 * failed_jobs where until today nobody was looking.
 *
 * ⚠️ IT SAYS NOTHING WHILE WHATSAPP IS UNCONFIGURED. Meta business verification
 * is the client's to complete and may take weeks. A weekly red line for a known
 * blocker is how a check trains people to skip it, and then the week it matters
 * looks like all the others.
 */
Schedule::command('realvows:whatsapp-templates-check')
    ->weeklyOn(1, '05:10')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

/*
 * ⚠️ A BACKUP NOBODY HAS RESTORED IS A FILE, NOT A BACKUP.
 *
 * There were none at all before this. mysqldump exits zero on a truncated
 * write, on a permissions fault halfway through, and on a table it quietly
 * skipped — so every run restores what it just took into a scratch schema and
 * compares row counts table by table before calling it a backup.
 *
 * 02:20 so it finishes well before the ledger check at 03:40 and the morning
 * report at 07:30, and so a bad night is already in the digest by breakfast.
 */
Schedule::command('realvows:backup')
    ->dailyAt('02:20')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

/*
 * ⚠️ THE RETENTION PERIOD IN THE PRIVACY POLICY, ENFORCED RATHER THAN STATED.
 *
 * Verification moving off the front door means an unconfirmed account can now
 * hold photographs of somebody's face and a third party's mobile number. That
 * category did not exist before and nothing was going to clear it.
 *
 * ⚠️ WEEKLY, NOT DAILY. Nothing here is urgent — the boundary is twelve months
 * and a record crossing it on Tuesday can be forgotten on Sunday. A daily
 * destructive task is a daily chance for a date bug to run.
 *
 * ⚠️ `--apply` IS PASSED HERE AND NOWHERE ELSE. Run by hand it reports and
 * deletes nothing, so anybody investigating can see the list first.
 */
Schedule::command('realvows:forget-abandoned --apply')
    ->weeklyOn(0, '04:40')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));
