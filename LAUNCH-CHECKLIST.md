# RealVows — launch checklist

**Nothing launches with an unticked box.**

Target is the **free launch**: introductions are free, payments arrive later as
a settings change. That removes the external blockers, not the build.

Legend — ✅ done · ⏳ waiting on someone · ⬜ not started · 🚫 blocked

---

## Part C — built, deployed, live

Asked three times, so it lives at the top now rather than inside a table.

| Piece | Where | Proof |
|---|---|---|
| Match inbox | `GET /introductions` | `InboxTest` — 9 tests |
| Express interest / pass | `POST /matches/{id}/respond` | `MutualInterestTest` — 14 tests |
| Mutual interest + contact reveal | `MatchInterest::isMutual()` | asserted from **both** sides |
| **The race** | `AnnounceMutualInterest::uniqueId()` | `test_simultaneous_interest_produces_one_event_and_two_notifications` ✅ |
| Blocking | `CandidatePool::notBlocked()` | `BlockingAndRaceTest` — 7 tests |
| Reporting | unchanged | `MatchViewTest` still green |

A member can sign up, verify, receive an introduction, open it, say yes or
pass, reach mutual interest, see contact details, and block. **Not a dead end.**

---

## Blocking the free launch

| | Item | Owner | State |
|---|---|---|---|
| ✅ | **Part C in full** — see the table above | Hassam | Live |
| ✅ | Free-period mode, tested **both directions** | Hassam | Live |
| ✅ | Free-period permanence guarantee asserted across the cutover | Hassam | Live |
| ✅ | Refund and cancellation policy written and linked | Hassam | Live, draft banner up |
| ✅ | Demo teardown built, dry-run default, real-purchase guard | Hassam | Built, **not yet run** |
| ✅ | Legal copy — Terms, Privacy, Guidelines, Refunds | Hassam | **Written in full.** Client released this: "the AI can give you a policy". Draft banner removed. |
| ⚠️ | **No lawyer in Jamaica has read the policies** | **Client** | Told him plainly. Not a reason to delay launch; a reason to get them reviewed after. |
| ✅ | Resend switch + webhook receiving live events | Hassam | Live, verified |
| ⏳ | **Verify the domain in Resend** — DNS is correct, button unpressed | **Client** | Blocks real sends |
| ⏳ | Scope webhook to `email.*` events only | **Client** | 2 minutes |
| ⏳ | Seven Meta templates approved | **Client** | **The only long pole left** |
| ⬜ | **Remove the "awaiting Meta review" line** from `partials/what-arrives.blade.php` the day the templates are approved | Hassam | Tied to the row above — the previous version of that line was stale for weeks and nothing could tell |
| ⬜ | Demo members actually removed | Hassam | Run on launch day |
| ⬜ | Five real-device checks | **Client** | |
| ⬜ | Backup taken and restore tested | Hassam | |

## Blocking payments (not the free launch)

| | Item | Owner |
|---|---|---|
| 🚫 | PowerTranz sandbox suite green | Client's bank |
| 🚫 | Billing legal entity confirmed | Client's banker |
| ⬜ | One live purchase, verified, refunded | Hassam |

## Not blocking anything

Domain migration · admin panel completion · monitoring · phone verification ·
documentation and handover.

---

## Standing green

| | |
|---|---|
| ✅ | **521 tests, 0 failures**, 9 skipped (PowerTranz, loudly) |
| ✅ | `PositiveControlsAreAliveTest` green |
| ✅ | Zodiac reader green over 60+ source files **and rendered output** |
| ✅ | Web root exposure green against production, in the deploy pipeline |
| ✅ | Cron confirmed firing (queue drains, watchdog armed) |
| ✅ | Maintenance watchdog verified by deliberately stranding the app (`MaintenanceWatchdogTest`) |

---

## ⚠️ The three that will bite if forgotten

**1 · The demo teardown is built and dry-run, and it will REFUSE until the
three demo purchases are cleared.** That refusal is the guard working: money
having changed hands is its signal that an account is real, and it cannot tell
a seeded purchase from a genuine one. Clearing those three rows is a deliberate
act that belongs to launch day, with somebody watching.

**2 · Meta template approval is the only long external pole left.** Everything
else on the free-launch list is hours of work or a client decision. Template
review is days, and rejection on the first attempt is normal.

**3 · There were never seven held receipts.** There are **three**, all belonging
to demo accounts at `.test` addresses, which is a reserved TLD that cannot
resolve. Sending them would have hard-bounced 100% of a sending domain's first
messages, on a domain with no reputation to spend.

The figure had been repeated across four documents without once being queried
against the database it described. Rule 5 now covers exactly this: a number
cited in a second report is re-derived, never repeated.

Resend is verified and live, and bounce detection is confirmed working against
real events. The three are closed by the demo teardown, not by dispatch.
