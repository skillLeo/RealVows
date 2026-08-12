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

**Part C is done.** `RiskAssessment` carries a score and reasons and has NO
verdict method — rejection is inexpressible rather than forbidden, asserted by
a test that checks the class has no `reject`/`decide`/`verdict` method and
carries a control so the assertion cannot pass vacuously. Rules do all the
arithmetic; `RiskNarrator` contributes prose only, never a number, never a
photograph, never a surname. A failing provider degrades to rules-only with a
visible note; no key at all is "off" rather than "unavailable", which are
deliberately different answers. `RiskScoringTest` 10/10.

**Part E is half done.** The `ai` settings group is seeded (both keys, provider,
per-feature models, per-feature toggles, monthly cap). The provider status
banner is NOT built.


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
  The entry point is a card on `/account` (not a fifth tab — Navigation
  documents four, never five), absent entirely when the policy does not offer
  it. Joint-photo upload built: the code is issued only after the contact signs
  in, kept stable across refreshes, stored on the private disk, and moves the
  record to `submitted` rather than `verified`. `GuardianJointPhotoTest` 5/5.
  All three guardian Vue pages written and compiled (`Dashboard`, `JointPhoto`,
  plus the two member pages), strings in the deck under `guardian_area`.
  Reminders at 3 and 7 days with a per-stage stamp so a daily cron cannot nag;
  at 14 days we stop chasing and leave the record alone rather than marking
  somebody rejected who has done nothing wrong. Reminders now actually send,
  re-issuing the link because tokens are hashed and cannot be read back.
  `TrustedContactReminderTest` 7/7. Admin queue built at
  `/admin/trusted-contacts`: contacts awaiting review AND members waiting for us
  to appoint one, gender-separated like verification, rejection requires a
  reason. `TrustedContactQueueTest` 6/6, component written with the
  no-contact queue placed FIRST because it is the one that gets forgotten.
  **B11 admin messaging built:** one counterparty per thread, so the schema has
  no shape for a member-to-member conversation, and the relay prohibition is
  rendered above the compose box from `AdminMessage::RELAY_PROHIBITION` rather
  than hardcoded in the template. Gender separation applies to threads too, and
  a trusted contact's thread is scoped on the member they serve rather than
  themselves. `AdminMessagingTest` 7/7.
  B8 replacement offer at 14 days and B9 unexplained-decline signal both in
  `GuardianGate`, with a test proving five declines WITH reasons do not trigger
  support — declining with a reason is the contact doing the job. The queue is
  linked from the verification screen with a combined count so the forgotten
  list is visible where staff actually look.

## Not started

- **Part B**: the support *ticket* itself (B9 detects the pattern and
  `TrustedContact/Show` does not yet render the offer or a way to raise it);
  no link from admin to an individual message thread — `/admin/messages/{user}`
  is reachable only by typing it
- **Part E** — the provider status banner (settings group is done)
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
- **Suite: 607 tests, 598 passing, 9 skipped** (PowerTranz sandbox ×7 and the
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

- **The six WhatsApp templates must be resubmitted to Meta.** All six are
  Active but were last edited 5 Aug, before the host changed — they still send
  members to `realvows.skillleo.com`. Changing a body invalidates the approved
  version, so this is unavoidable.
- **`phone_verification_code` has never been submitted.** The code declares it;
  Meta does not have it. Phone verification fails silently the day it is
  switched on.
- **An eighth template** is needed for the trusted-contact invitation, or
  invitations go by email only.
- **WhatsApp credentials are not on the new server.** All eight settings are
  empty at `/admin/settings`. WABA ID `1553517519889000`, business ID
  `1896803604614710`. Needs phone number ID, permanent access token, app secret
  and a verify token.
- `guardian_requirement` is unset by design. Somebody has to choose.
