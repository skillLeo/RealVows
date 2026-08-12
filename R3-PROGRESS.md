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

- **Part B remainder.** Built since the last handoff: `GuardianInvitation`
  (hashed single-use token, plaintext returned once, re-issue invalidates the
  previous), `GuardianGate` (the three B5 gates, each reading the policy first),
  and the `guardian` settings group seeded with the requirement unset.
  `GuardianGateTest` 8/8 covering all four levels.
  **All three B5 gates are enforced, not just built.** Pool: `CandidatePool`
  filters ungated candidates out of the query, and `MatchGenerator` refuses to
  write rows for an ungated seeker. Introduction: `DashboardController` filters
  unapproved introductions out of the payload rather than hiding them in the
  component. Contact: `MatchPresenter` withholds the last name and contact
  details until the contact gate is approved. The dashboard also carries
  `trustedContact` state so a waiting member is always told why.
  `GuardianGateEnforcementTest` 7/7, every refusal paired with a control.
  `GuardianService` handles nomination (integrity checked BEFORE anything is
  sent), the no-contact path (B7, marks `is_appointed`), and acceptance (the
  guardian creates their own account with their own password, single-use
  token). `GuardianNominationTest` 9/9.
  **The member-facing flow is live over HTTP:** `/trusted-contact` to name one
  or request an appointed contact (404s while the requirement is off),
  `/trusted-contact/accept/{token}` public so an invited person can create
  their account. `TrustedContactInvitation` mailable plus HTML and text views.
  `TrustedContactFlowTest` 8/8. Both Vue pages are written, compiled and in the
  manifest; every string went through the copy deck under `trusted_contact`.

## Not started

- **Part B**: joint-photo upload with the generated code; no-guardian admin
  queue; 3/7/14 reminders; unfair-guardian path; admin messaging with the relay
  prohibition in the UI. Nothing links to `/trusted-contact` from the product
  navigation yet — the pages exist and are reachable only by typing the URL.
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
- **Suite: 569 tests, 560 passing, 9 skipped** (PowerTranz sandbox ×7 and the
  web-root check ×2, all needing a live host)

## Blocked, not forgotten

- **WhatsApp delivery of the trusted-contact invitation cannot be built.** B3
  asks for the invitation to go by email and WhatsApp. A business-initiated
  message to somebody who has never contacted us can only be sent from an
  approved template, and none of the seven is an invitation. This needs an
  **eighth template** submitted to Meta. Email works; the WhatsApp half is
  blocked on review. Recorded in `GuardianService` rather than sending email
  only and calling B3 done.

## Needs the client, not the developer

- **The six WhatsApp templates must be resubmitted to Meta.** Changing a body
  invalidates the approved version. Six as Utility, `phone_verification_code`
  as Authentication.
- `guardian_requirement` is unset by design. Somebody has to choose.
