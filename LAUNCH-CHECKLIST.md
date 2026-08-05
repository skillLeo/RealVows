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
| ⏳ | Legal copy — Terms, Privacy, Guidelines | **Client** | Draft banners up |
| ⏳ | Refund policy wording approved | **Client** | Drafted, awaiting approval |
| ✅ | Resend switch + webhook receiving live events | Hassam | Live, verified |
| ⏳ | **Verify the domain in Resend** — DNS is correct, button unpressed | **Client** | Blocks real sends |
| ⏳ | Scope webhook to `email.*` events only | **Client** | 2 minutes |
| ⏳ | Seven Meta templates approved | **Client** | **The only long pole left** |
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
| ✅ | **511 tests, 0 failures**, 9 skipped (PowerTranz, loudly) |
| ✅ | `PositiveControlsAreAliveTest` green |
| ✅ | Zodiac reader green over 60+ source files **and rendered output** |
| ✅ | Web root exposure green against production, in the deploy pipeline |
| ✅ | Cron confirmed firing (queue drains, watchdog armed) |
| ⬜ | Maintenance watchdog verified by deliberately stranding a staging deploy |

---

## ⚠️ The three that will bite if forgotten

**1 · The demo teardown is built but has never been run.** It defaults to a dry
run, so the first real execution is on launch day under time pressure. Run
`php artisan realvows:teardown-demo` (no flags) against production **now** and
read the counts. If the numbers surprise you, that is the moment to find out —
not with `--execute` typed.

**2 · Meta template approval is the only long external pole left.** Everything
else on the free-launch list is hours of work or a client decision. Template
review is days, and rejection on the first attempt is normal.

**3 · Resend still has the domain unverified, so real sends are blocked.** All
four DNS records are correct and resolving — DKIM, the `send.` SPF, the
return-path MX and DMARC. The account is simply still in test mode, which means
only `realvows@gmail.com` can receive anything.

Until that button is pressed the seven held receipts cannot go out, because
they are addressed to real members. Bounce detection itself is **working** and
verified against live events.
