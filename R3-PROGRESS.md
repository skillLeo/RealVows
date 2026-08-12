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

- `DO NOT "HARDEN" THIS` note for the `guardian_requirement` fail-open. A future
  reader will see fail-open on a compliance setting and try to invert it. The
  argument: failing closed would lock every member out of the matching pool
  during a database fault, which is worse than being briefly less strict.
- **Non-binary reviewer branch** — currently routes `non_binary` to the female
  reviewer, which is my assumption and not a decision anybody made. Resolve from
  the real gender enum: if it only permits man/woman, delete the branch rather
  than leave dead code implying a policy. If it permits more, route those records
  to the super-admin queue instead of assigning by assumption.
- **False-range sweep** — `0[2-9]` reads as a range and is a set. It hid the
  10th to the 31st of a month from `MigrationReversibilityTest`. Sweep every
  regex, glob and date pattern of that shape.

## Facts

- **Live: https://realvows.com** on the client's VPS (2.25.102.137, Ubuntu 24.04,
  nginx, PHP 8.4, MariaDB, Let's Encrypt to 10 Nov 2026, cron every minute)
- **Staging: realvows.skillleo.com** — nothing user-facing points here
- Mail: Hostinger SMTP as `info@realvows.com`. No delivery webhook, so the
  ledger can confirm hand-off and never arrival
- Demo logins are ON (`APP_ENV=demo`, `REALVOWS_DEMO_LOGINS=true`)
- Free period is ON; founding-offer band counts verified members
- **Suite: 534 tests, 525 passing, 9 skipped** (PowerTranz sandbox ×7 and the
  web-root check ×2, all needing a live host)

## Needs the client, not the developer

- **The six WhatsApp templates must be resubmitted to Meta.** Changing a body
  invalidates the approved version. Six as Utility, `phone_verification_code`
  as Authentication.
- `guardian_requirement` is unset by design. Somebody has to choose.
