# RealVows — launch checklist

**Nothing launches with an unticked box.**

Target is the **free launch**: introductions are free, payments arrive later as
a settings change. That removes the external blockers, not the build.

Legend — ✅ done · ⏳ waiting on someone · ⬜ not started · 🚫 blocked

---

## Blocking the free launch

| | Item | Owner | State |
|---|---|---|---|
| ✅ | Match inbox, filter by state only, no search surface | Hassam | Live |
| ✅ | Express interest / pass, both final | Hassam | Live |
| ✅ | Mutual interest + contact reveal, race tested | Hassam | Live |
| ✅ | Blocking in the engine's hard filters, symmetric | Hassam | Live |
| ✅ | Free-period mode, tested **both directions** | Hassam | Live |
| ✅ | Free-period permanence guarantee asserted across the cutover | Hassam | Live |
| ✅ | Refund and cancellation policy written and linked | Hassam | Live, draft banner up |
| ✅ | Demo teardown built, dry-run default, real-purchase guard | Hassam | Built, **not yet run** |
| ⏳ | Legal copy — Terms, Privacy, Guidelines | **Client** | Draft banners up |
| ⏳ | Refund policy wording approved | **Client** | Drafted, awaiting approval |
| 🚫 | Resend provider switch + webhook | Hassam | **Needs API key** |
| ⏳ | Seven Meta templates approved | **Client** | Submitted? |
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
| ✅ | **494 tests, 0 failures**, 9 skipped (PowerTranz, loudly) |
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

**3 · Email cannot currently detect a bounce.** A hard bounce to a dead address
is indistinguishable from a message that was read. The ledger admits this —
`accepted`, labelled *"Handed to the mail server"* — but the fallback chain
cannot report a hole in itself until the provider webhook lands. See
[docs/EMAIL-DELIVERABILITY.md](docs/EMAIL-DELIVERABILITY.md).

Defensible with thirty invented members. **Not defensible on the day a real
member is told about an introduction and never receives it** — which, on a free
launch, is the only thing the product does.
