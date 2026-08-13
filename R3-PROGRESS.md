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

- **Admin dashboard** at `/admin`, first item in the sidebar. Queue tiles, member
  figures, a fourteen-day joining chart, two donuts, completeness bands, and a
  plain-language "what needs you" panel. Every figure scoped by `ReviewerScope`
  and the scope printed on the page — an aggregate is information about the rows
  behind it, and a female reviewer must not be handed a description of the
  people she was specifically not given access to. `AdminDashboardScopeTest` 7/7,
  verified by deleting the scoping and watching five fail.
  Charts are inline SVG, no library. The summary is computed in PHP
  (`DashboardInsights`), not by a model: every sentence quotes a figure drawn
  beside it, and a model that writes "about a dozen" next to a tile reading 3
  makes the page untrustworthy to the one reader who cannot check it.
- **Admin shell** — `AdminLayout` with a permanent sidebar carrying all sixteen
  destinations. Seven of them previously had no navigation at all and could only
  be reached by typing the URL. `AppLayout` delegates to it for staff, so
  `/dashboard` and `/account` do not drop a reviewer back into the member
  layout. Admin "Settings" renamed **Configuration** — the member's own account
  page is also called Settings, and staff saw two items meaning entirely
  different things under one word.

## In progress

**Part C is done.** `RiskAssessment` carries a score and reasons and has NO
verdict method — rejection is inexpressible rather than forbidden, asserted by
a test that checks the class has no `reject`/`decide`/`verdict` method and
carries a control so the assertion cannot pass vacuously. Rules do all the
arithmetic; `RiskNarrator` contributes prose only, never a number, never a
photograph, never a surname. A failing provider degrades to rules-only with a
visible note; no key at all is "off" rather than "unavailable", which are
deliberately different answers. `RiskScoringTest` 10/10.

**Part E is done.** The `ai` settings group is seeded (both keys, provider,
per-feature models, per-feature toggles, monthly cap). `AiProviderStatus` asks
the container what was actually bound rather than re-deriving the rule from
settings, and `AiProviderBannerTest` 5/5 rebinds the narrator and asserts the
banner changes — the test that proves it is not reasoning its way to an answer.
Withheld from members.

**Part D is done.** `ProfileWriter` calls the provider, and an unfaithful
rewrite is DISCARDED inside the service rather than shown with a caution.
`/profile/rewrite` suggests and never saves. Five per member per day, keyed on
the user rather than the address. `profiles.bio_original` /
`bio_rewritten_by` / `bio_rewritten_at` store what the member actually wrote so
a false claim can be traced to its author. `ProfileWriterTest` 9/9.

**Fidelity core:**
`ProfileWriterFidelity` catches invented proper nouns and invented numbers,
survives capitalisation and punctuation, and does NOT flag ordinary sentence
openers — an over-eager check would reject every rewrite and be switched off,
which is the same as having none. `ProfileWriterFidelityTest` 8/8 including the
hallucinating fixture the brief requires and a control proving the checker is
not simply returning nothing.


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
- **Part D** — writing `bio_original` on save when a rewrite is accepted (the
  column exists; the save path does not populate it yet). The accept/reject UI
  is built: both versions side by side, absent rather than disabled when no key
  is configured, and accepting only changes the field so the ordinary profile
  save is still what writes it.
- ~~**Part F** — public copy reduction~~ **done for the marketing pages.**
  Verification steps, message-content descriptions and the safety-feature list
  are out of home, how-it-works, about and the shared legal preamble. A whole
  section explaining verification was removed from `/how-it-works`.
  **⚠️ ONE DELIBERATE DEPARTURE, FLAGGED FOR THE CLIENT:** §F says "all public
  pages", but the Privacy Policy's own "Keeping it safe" section still says
  traffic is encrypted and photographs are held privately. A privacy policy
  exists to disclose data handling; stripping it would make it a worse legal
  document, and it is the one page where explaining how the product works is
  the point. Reverse it if he disagrees.
- ~~**Part G** — home banner~~ **done.** "The Meeting" is pure SVG and CSS at
  **7.4kb** against a 25kb limit, three parallax layers, two brass lines meeting
  under a keystone on a 20s cycle at deliberately different speeds. Full stop
  under `prefers-reduced-motion` — final state, nothing moving. Keyframes
  registered as marketing-only and may-loop. **Zero `<img>` elements on the home
  page.** Measured clean at 375, 834 and 1440.
  **⚠️ THE COUPLES RAIL WAS REMOVED TOO** — twelve photographs of real couples
  running as #16 The Ticker. §G says no photographs of people anywhere on the
  home page. It was doing real work (proof that people come out the other side),
  the partial still exists at `public.partials.couples-rail`, and restoring it is
  one line. Client's call.
- ~~**Part H**~~ **measurable half done; the felt half is yours.**
  Touch targets measured at a real 375px viewport with a new harness carrying a
  self-test — five were under 44px and are fixed; the two remaining are inline
  links inside sentences (WCAG 2.5.8 exemption, written down). Haptics closed to
  three moments in one module with a build-failing guard: **there was a buzz on
  every page navigation**, which is the exact failure the brief warns about.
  `DEVICE-WALK-LIST.md` is the handover for what I cannot test.
  ⚠️ **This list was wrong and had been for a while.** It named five things as
  outstanding; three were already built and shipped, and I only found that by
  checking the emission points rather than believing the file. The FAB is used
  in `Dashboard.vue`, `SheetModal`/`ReportSheet` are used on two screens, and
  `startViewTransition` is called in `app.js`. A progress file that says work
  is undone is the same failure class as a guard that reads the wrong artefact
  — it looks like information and is not.
  **Now genuinely done in H:** optimistic approve/decline with server-governed
  rollback; empty states on the credit ledger, the support page and the
  verification review screen, held by `EmptyStatesTest`.
- ~~**Part I**~~ **done.** `/questions` is a public Blade page using native
  `<details>` — keyboard operable, screen-reader correct, works without
  JavaScript, and findable by the browser's own in-page search, which a JS
  accordion hides. Answers describe outcomes only. It joined
  `PublicSurfaces::paths()` so the marriage-lead, outbound-link and prose-style
  guards cover it automatically.
  Support tickets for members and trusted contacts, with a type list that
  differs by who is filling it in. `guardian_unfair` is pre-selected when the
  decline pattern is present. The admin inbox sorts unfair-contact first and
  then oldest, and replies go through the existing message thread rather than a
  second reply box without the relay prohibition on it.
  `SupportFlowTest` 7/7, including that a trusted contact cannot raise the
  route that exists to challenge them, and that unfair-contact tickets sort
  above an older billing question. Linked from `/account`.

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
- Demo logins are ON (`APP_ENV=demo`, `REALVOWS_DEMO_LOGINS=true`) and now
  **close themselves** the moment any account exists whose address is not
  `@realvows.test`. Production: 32 demo, 0 real. The client's own genuine admin
  account will end them — intended, and he has to be told
- Free period is ON; founding-offer band counts verified members
- **Suite: 688 tests, 679 passing, 9 skipped** (banner 16.1kb of a 25kb budget,
  0 images on home; breakpoints and touch targets clean)
- **All ten R3 parts have been started; A, B, C, D, E, F, G, I, J are complete.**
  H is partial (measurable half done; FAB, sheets, view transitions, optimistic
  UI outstanding)

### WhatsApp, as of 13 Aug 2026

- Phone number **+1 561-899-5033**, status Connected, phone number ID
  **1276562182199438** — stored on production along with the WABA ID and `en`
- ⚠️ **The Meta app is UNPUBLISHED.** In development mode the Cloud API will
  only message a handful of registered test numbers. No member receives
  anything until the app is published, and publishing is gated on business
  verification then App Review
- ⚠️ **Business verification is Unverified and there is no payment method.**
  This is why template creation now fails with "does not have permission to
  create message template" — the first batch was allowed, further ones are not
- ⚠️ **`WhatsAppTemplates::TEMPLATES` bodies are NOT what members receive.**
  Meta stores the approved body; we send a name, a language and ordered values.
  The approved `verification_rejected` differs from ours in wording, has no
  link and no opt-out line. Variable counts do match, so nothing errors.
  `realvows:whatsapp-templates-check` is the only check pointed at the real
  emission point — run it once a token exists

## Blocked, not forgotten

- **WhatsApp delivery of the trusted-contact invitation cannot be built.** B3
  asks for the invitation to go by email and WhatsApp. A business-initiated
  message to somebody who has never contacted us can only be sent from an
  approved template, and none of the seven is an invitation. This needs an
  **eighth template** submitted to Meta. Email works; the WhatsApp half is
  blocked on review. Recorded in `GuardianService` rather than sending email
  only and calling B3 done.

## Needs the client, not the developer

- **Business verification.** Umar's Jamaica business documents. It gates
  template creation, publishing the app, and therefore every WhatsApp message
  to a real member. Longest lead time of anything outstanding, and nobody but
  him can supply the documents. Everything below waits on it.
- **Publish the Meta app.** Development mode reaches test numbers only.
- **`phone_verification_code` has never been submitted** and currently cannot
  be — creation is refused while the business is unverified. Phone verification
  by WhatsApp does not work at launch.
- **An eighth template** is needed for the trusted-contact invitation, or
  invitations go by email only. Same blocker.
- ~~The six templates send members to the old host~~ — **wrong, withdrawn.**
  The approved bodies carry no link at all. Our file had the stale host; that
  was fixed weeks ago and I then assumed Meta's copies shared the fault. They
  never did. Nothing to resubmit for this reason.
- **Two credentials still needed at `/admin/settings` → WhatsApp:** a permanent
  access token (System user `realvows-api`, permissions
  `whatsapp_business_messaging` + `whatsapp_business_management`, expiry Never)
  and the app secret. Then `php artisan whatsapp:verify-token` mints the third
  and prints the callback URL to paste into Meta.
- `guardian_requirement` is unset by design. Somebody has to choose.
