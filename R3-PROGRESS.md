# R3 Progress

**This file is the memory between sessions. Update it before every handoff.**

## Done

- **Part A** — affinity engine removed (matrix, scorer, calculator, both profile
  columns, stored component), reweighted to preferences 28 / values 25 /
  lifestyle 17 / personality 17 / physical 13, migrated with a dry-run command,
  forbidden-terms allow-list now empty and running at full strictness
- **Part J** — gender-separated reviewers, enforced at the query layer and
  re-asked at the image-streaming endpoints, 404 never 403, super-admin views
  logged. `ReviewerScopeTest` 8/8
- **Part B core** — schema (`guardians`, `guardian_decisions`,
  `guardian_invitations`), `GuardianPolicy` (four levels, default `off`),
  `Guardian` + `GuardianDecision` models, `GuardianPresenter`,
  `GuardianIntegrity`, `GuardianDashboardController`, `EnsureUserIsGuardian`,
  routes
- **Checkpoint 1** — `GuardianPhotoRuleTest` 5/5 including the positive control
  proving a family guardian *does* receive photographs
- **WhatsApp template hosts corrected** to realvows.com; `ProductionHostTest`
  4/4 guards templates, rendered mailables and signed links
- **SSH password auth disabled** on the VPS, key-only, verified from outside

## In progress

- Part B remainder

## Not started

- **Part B**: invitation flow, joint-photo upload, three approval gates wired
  into matching, no-guardian admin queue, 3/7/14 reminders, unfair-guardian
  path, admin messaging with the relay prohibition rendered in the UI
- **Part C** — AI risk scoring
- **Part E** — API keys and settings, provider status banner
- **Part D** — AI profile writer + fidelity test
- **Part F** — public copy reduction
- **Part G** — home banner ("The Meeting")
- **Part H** — premium and mobile pass + device walk-list
- **Part I** — FAQ and support

## Carried debt

*(cleared 13 Aug 2026 — kept here as the record of what was decided)*

- ~~`DO NOT "HARDEN" THIS` note for `guardian_requirement`~~ — written into
  `GuardianPolicy::level()`. One failure mode is silent and permanent (nobody
  matched, discovered days later); the other is loud, brief and reversible.
- ~~Non-binary reviewer branch~~ — the enum permits `non_binary` and the profile
  request validates it, so it is genuinely selectable; zero members have chosen
  it. Neither reviewer now sees those records. They queue for super admin rather
  than being handed to whoever the code assumed. The decision is the client's to
  make and is one branch when he makes it.
- ~~False-range sweep~~ — no live instances remained. Turned into
  `FalseRangePatternTest` rather than left as a grep nobody can re-run, with a
  control that exercises the real extraction path. `AssertionsCanFailTest`
  caught the first version, whose control asserted on the regex without running
  the sweep.

## Facts

- **Live: https://realvows.com** on the client's VPS (2.25.102.137, Ubuntu 24.04,
  nginx, PHP 8.4, MariaDB, Let's Encrypt to 10 Nov 2026, cron every minute)
- **Staging: realvows.skillleo.com** — nothing user-facing points here
- Mail: Hostinger SMTP as `info@realvows.com`. No delivery webhook, so the
  ledger can confirm hand-off and never arrival
- Demo logins are ON (`APP_ENV=demo`, `REALVOWS_DEMO_LOGINS=true`)
- Free period is ON; founding-offer band counts verified members
- **Suite: 537 tests, 528 passing, 9 skipped** (PowerTranz sandbox ×7 and the
  web-root check ×2, all needing a live host)

## Needs the client, not the developer

- **The six WhatsApp templates must be resubmitted to Meta.** Changing a body
  invalidates the approved version. Six as Utility, `phone_verification_code`
  as Authentication.
- `guardian_requirement` is unset by design. Somebody has to choose.
