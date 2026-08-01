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
