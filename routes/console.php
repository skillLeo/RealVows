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
|   * * * * * cd /path/to/realvows && php artisan schedule:run >> /dev/null 2>&1
|
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
    ->withoutOverlapping();

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
 * Tops up introductions for anyone verified whose set has gone stale. Queues
 * jobs rather than doing the work, so the drain above does it a chunk at a
 * time and being killed mid-run costs at most one chunk.
 */
Schedule::command('realvows:refresh-matches')
    ->dailyAt('03:10')
    ->withoutOverlapping();

/*
 * Finds purchases the gateway never told us the outcome of. This is what
 * saves a member who paid and never got their credits because a callback was
 * lost — without it, the only way that surfaces is a complaint.
 */
Schedule::command('realvows:reconcile-payments')
    ->hourly()
    ->withoutOverlapping();

/*
 * Walks every credit chain and proves nobody's balance drifted. Reports
 * rather than repairs — an automatic fix to a money record would destroy the
 * evidence of whatever caused the problem.
 */
Schedule::command('realvows:verify-ledger')
    ->dailyAt('03:40')
    ->withoutOverlapping();
