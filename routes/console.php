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
