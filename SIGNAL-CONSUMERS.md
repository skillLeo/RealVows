# Every signal this system raises, and who consumes it

**An alarm nobody hears is worse than no alarm, because it makes people believe
the thing is watched.**

This file exists because of `f9c2652`. CI went red on the day a fault was
introduced, named the exact failing test, and was deployed anyway. Twice. The
check saw perfectly, reported honestly, and nobody was listening.

The first ten faults in this project were *a check that could not see*. That one
was *a check that saw and was not heard*, and once you name it you find it
everywhere: this codebase had built a great many alarms and almost no listeners.

Audited 2026-08-14. Every row was verified on the live server, not inferred.

---

## The two that were arriving nowhere

### 1. The scheduler itself — **was `>> /dev/null 2>&1`**

The cron line on the VPS discarded every byte the scheduler produced. If
`schedule:run` had failed — a PHP fatal, an unreadable `.env`, a database that
would not answer — nothing would have recorded it, and the process responsible
for running *every other check on this list* was the only one reporting to
nobody.

**Fixed.** Output goes to `storage/logs/scheduler.log`, truncated nightly at
5MB so an unbounded log cannot fill a 2 vCPU disk.

**And the outer redirect was only half of it.** Laravel sends each scheduled
command's own output to `/dev/null` unless told otherwise, so fixing the cron
line alone bought a log that said `DONE` and never said what was found. The five
commands that can *discover* something now call `appendOutputTo`. The ones that
only do work — draining the queue, rewriting a projection every minute — do not,
because a log nobody can skim is the same void with more disk usage.

### 2. Stored file paths — **nothing checked them at all**

Not a signal that went unheard. A signal that was never raised.

`DemoSeeder` wrote `guardians/demo/together.jpg` into 22 guardian rows and never
created the file. **All 22 were missing on production.** That photograph is the
primary check in the guardian system: a reviewer approves a trusted contact by
looking at the two of them holding a code issued minutes before. Every one of
those reviews threw, on the only screen the check happens on.

The seeder reported success throughout, because a path in a column looks exactly
like a photograph until somebody opens it.

**Found by reading a production log by hand.** That is not a strategy, so it is
now `realvows:check-stored-files`, scheduled daily, exiting non-zero when a
record names a file that does not exist. It reports and never repairs: deleting
the row would destroy the evidence of whatever lost the file, and writing a
placeholder would turn a visible fault into a plausible-looking one.

---

## The full inventory

| Signal | Raised by | Goes to | Consumer | If nobody reads it |
|---|---|---|---|---|
| **Credit ledger discrepancy** | `realvows:verify-ledger`, daily 03:40 | Email to `admin_notification_email` | `info@realvows.com` — **transport verified working 2026-08-14** | A member's balance disagrees with their ledger and nobody knows. Money. |
| **Stored file missing** | `realvows:check-stored-files`, daily 04:17 | `scheduler.log`, non-zero exit | Scheduler log | A screen 500s for whoever clicks it. This is how the 22 guardian photos hid. |
| **Broken pipeline** | `realvows:maintenance-watchdog`, every minute | `laravel.log` at error level | **Nothing tails this** | See below. |
| **Failed jobs** | Queue, `--tries=3` | `failed_jobs` table | **Nothing reads this table** | See below. |
| **Delivery failure** | `MessageDelivery` rows, `status=failed` | Database | Admin Deliveries screen | Visible only if somebody opens it. |
| **Payment mismatch** | `realvows:reconcile-payments`, hourly | `scheduler.log` | Scheduler log | A purchase settles and no credit is granted. |
| **Uncaught exception** | Laravel handler | `laravel.log` at error | **Nothing. No error tracking is wired.** | See below. |
| **CI failure** | GitHub Actions | Run status | **Deploy now refuses to run** | Was: deployed anyway, twice. |
| **Deploy failure** | GitHub Actions | Run status | Whoever dispatched it | Manual dispatch, so someone is watching. |

---

## The three with no consumer, stated plainly

I have not invented listeners for these, because a listener nobody checks is the
same problem wearing a hat. They need a decision, not code.

**1. `laravel.log` — 32 error-level lines on production, unread.**
Reading them today found the missing guardian photographs *and* the `--no-dev`
seeder failure, both recorded honestly at the moment they happened. No error
tracking is installed. The realistic options are Sentry's free tier, or a daily
digest of new error lines to `info@realvows.com`. **Doing nothing is also a
choice, and it should be a stated one.**

**2. `failed_jobs` — nothing reads the table.**
A job that fails three times lands here and stops. On this host that includes
outbound email and WhatsApp delivery, so the failure mode is a member silently
never hearing about an introduction. `realvows:show-deliveries` exists and must
be run by hand.

**3. `info@realvows.com` — the address every alert converges on.**
It is configured, SMTP works, and a test message was delivered from the server
on 2026-08-14. **Whether a human opens it is the one thing I cannot verify.** If
nobody does, the ledger alert is decoration and the row above it is a lie.

---

## The rule going forward

Before adding an alarm, name its consumer. If you cannot, do not add it — write
down that the condition is unwatched instead. An honest gap is cheaper than a
false sense of coverage, and this project has now paid for that lesson eleven
times.
