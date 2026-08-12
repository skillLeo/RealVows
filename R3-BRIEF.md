# RealVows — Build Brief R3: Guardian System, AI, and Premium Redesign

**Agreement SKL-2026-RV-001. Live at https://realvows.com. Client: Umar, Jamaica, non-technical.**

Removes the platform's original differentiator, adds a guardian system in its
place, adds two AI features, and rebuilds the visual and mobile experience.

Work in the order in §12. `R3-PROGRESS.md` is the memory between sessions.

---

## 1. STANDING CONSTRAINTS

- **No browsing, ever.** Filtering by state is navigation. Searching by attribute is browsing. Browsing is banned.
- **Rule 5 in full.** Every test, guard, measuring harness or one-off audit carries a positive control in both directions. A reader that matches nothing and a reader that matches everything are equally dead. Exposure questions are answered from emission points, not from data. A figure appearing in a second report is re-derived, never repeated.
- **All `DO NOT "HARDEN" THIS` decisions stand.**
- **Design tokens locked.** Oxblood, brass, cream, ink. No new colours. No shadows ever. 8px base, 8px radius. Fraunces display with WONK/SOFT at 56/40 only, numerals WONK 0 tabular. Inter for UI.
- **Motion catalogue:** sixteen registered primitives. `MotionCatalogueTest` stays green. #15 The Gleam and #16 The Ticker are marketing-only and may never appear on an authenticated route.
- **Public layout ships no Vue, no Inertia, and reads no database.**
- **Hostinger heritage:** MySQL/MariaDB, database queue driver, no Redis, cron every minute.
- **Never describe any background process as instant.**
- **Commits authored `skillLeo <hassam.dev.571@gmail.com>`.** No co-author trailers, no generated-with lines.

---

## 2. PART A — REMOVE THE AFFINITY ENGINE ✅ DONE

Weights: preferences 28, values 25, lifestyle 17, personality 17, physical 13.
Total 100. Threshold 65. Teaser count unchanged.

Delete the matrix, the scorer, the calculator. Migration drops the derived
columns and strips the stored component; dry-run first. The forbidden-terms
guard keeps full strictness with an empty allow-list — it now exists because
the mechanism does not exist. Any copy implying a hidden or proprietary method
comes out; matching is now fully explainable.

---

## 3. PART B — THE GUARDIAN SYSTEM

Called **"trusted contact"** on every member-facing surface. Never "wali",
never "guardian" in user copy. Internally `guardian`.

### B1. Configurability

`guardian_requirement` — one of `required_all`, `required_female`, `optional`,
`off`. Every gate reads it. Changing policy never requires a deploy. Test all
four states. **Ship unset; do not pick a default that looks like a decision.**

### B2. Data model

`guardians` — member_id, name, relationship enum (father, brother,
paternal_grandfather, paternal_uncle, other_male_relative, appointed), phone,
email, user_id, is_appointed, status enum (invited, registered, submitted,
verified, rejected, replaced), risk_score, risk_reasons, joint_photo_path,
verified_at, verified_by, rejection_reason, device_fingerprint_hash.

`guardian_decisions` — append-only: guardian_id, match_id, decision, reason, decided_at.

`guardian_invitations` — token, expiry, used_at.

The guardian receives a real `User` account with role `guardian`.

### B3. Verification flow

1. Member supplies name, relationship, phone, email during profile completion
2. Uniqueness validated **before sending anything** (B4)
3. Invitation by email and WhatsApp
4. Guardian opens the link, creates **his own account with his own password**
5. Guardian is shown a generated code and uploads **a photograph of the member and himself together, holding that code**
6. Device fingerprint captured on first sign-in
7. Risk score computed (Part C)
8. Admin reviews the joint photograph and the score
9. Only on approval does the member enter the matching pool

**The joint photograph is the primary check. No government ID is collected
anywhere** — the relationship cannot be proved from Caribbean documents and
storing identity documents creates risk without benefit. Record the reasoning.

### B4. Uniqueness and integrity

- Guardian phone or email already on **any** member account → hard reject
- Guardian email is a plus-alias or dot-variant of the member's → hard reject
- Guardian phone already guardian for more than 3 members → flag, do not reject
- Impossible age gap (father ≥16, grandfather ≥32, brother ±25) → flag
- Declared age is self-reported; the joint photograph is what the admin judges

### B5. Approval gates

1. **Pool entry** — verified before the member is matched at all
2. **Introduction release** — guardian sees the other party first and approves
3. **Contact exchange** — guardian approves before contact details move

Each writes to `guardian_decisions`.

### B6. Photographs and the appointed-guardian rule ✅ DONE

- A **family** guardian is a mahram and **may** receive photographs
- An **appointed** guardian is a stranger and **must never** receive photographs of the member or the other party

Appointed cases: photographs held by the female admin, released to the member
directly at the approved stage. Enforced in the presenter — the single emission
point — with a test and positive control.

### B7. The no-guardian path

Member selects **"I do not have a trusted contact."** No name field, no phone
field, no list. Goes to a dedicated admin queue. Admin assigns an appointed
guardian or acts as intermediary. Marked `is_appointed = true`, which triggers B6.

No public list of imams or organisations — nothing to type and nothing to abuse.

### B8. Guardian goes silent

Reminder at 3 days, second at 7. At 14 days the member is offered a replacement,
which restarts verification. The dashboard always shows the true state. Never
let a flow deadlock with no visible reason.

### B9. Unfair guardian

Support ticket type "my trusted contact is not responding fairly", auto-suggested
after 5 declines with no reason. Admin reviews decision history and may replace
with an appointed guardian. Resulting contact happens with the admin recorded as present.

### B10. Guardian dashboard

Own login. Pending introductions for their ward, the other party's profile and
photographs (family only), approve/decline with reason, message the admin.
Nothing else. No browsing, no search, no other members.

### B11. Admin may contact both

Threaded, logged, visible in the member's history. **Hard rule written into the
interface itself:** admin never relays a message between two members. That
sentence sits above the compose box.

---

## 4. PART C — AI RISK SCORING

0–100 score plus a plain-English reason list, on member and guardian verification.

**Deterministic rules compute most of it.** Do not send to a model what a rule
can decide.

| Signal | Points |
|---|---|
| Same device fingerprint as the member | 40 |
| Joint photograph missing, or only one person visible | 50 |
| Guardian has never signed in independently | 25 |
| Guardian activity always within 60s of member activity | 20 |
| Age gap impossible for stated relationship | 40 |
| Contact details resemble the member's | 30 |
| Guardian phone matches a member account | auto-reject |

**The model adds judgement, not arithmetic.** One call, structured input, no
images: plausibility of name/relationship, whether free text reads naturally,
a two-sentence summary. Cheapest capable text model, structured JSON out.

**Rules that must hold:**
- **The AI never rejects anyone.** Build it so auto-rejection is not expressible.
- No photographs sent to any model.
- Score, reasons and model version stored for audit.
- API unavailable → rules-only scoring with a visible note. An outage never blocks members.

Thresholds: <30 suggested approve, 30–60 review, >60 flagged, admin must enter a reason to approve.

---

## 5. PART D — AI PROFILE WRITER

Member types their bio however rough; AI returns a warm, correct version.
Member sees both and chooses.

**Hard rules:**
- **Only facts the member supplied.** Never invent a job, hobby, family detail, place or attribute. A profile stating untrue things is deception.
- No physical description added or embellished
- No romantic, flirtatious or suggestive register
- Member must explicitly accept before it saves
- **Store the original alongside the rewrite**

`ProfileWriterFidelityTest`: every proper noun and factual claim in the output
must appear in the input. Positive control: a deliberately hallucinating fixture
must fail.

Scope: bio and guided prompt answers. Never messages between members — there are none.
Rate limit 5 rewrites per member per day.

---

## 6. PART E — API KEYS AND SETTINGS

New **AI** group in `/admin/settings`: OpenAI key, Anthropic key, active provider,
model name per feature, per-feature toggles, monthly spend cap with an alert at 80%.

Encrypted at rest. Never in the repository. Never logged.

**Provider status banner** in the same family as the gateway indicator — asks the
container what was actually bound rather than re-deriving the rule. Test rebinds
the container and asserts the banner changes.

No key present → risk scoring falls back to rules only, profile writer hides its
button. Nothing breaks.

---

## 7. PART F — PUBLIC COPY: SAY LESS

**The product does things, it does not explain how it does them.**

Remove from all public pages: any description of the trusted-contact mechanism,
any list of security features, any explanation of verification steps, any
description of what a message contains, any "nobody can…" construction.

Serious people are reassured by restraint, not by a list of controls. Publishing
the controls tells anyone who wants to work around them where to start.

The trusted-contact step appears **in the signup flow**, at the moment it is
needed. It is not marketing copy.

---

## 8. PART G — THE HOME BANNER: "THE MEETING"

Remove the photographic mosaic entirely. No photographs of people on the home page.

**Composition:** a colonnade of arches in brass hairlines against
`--oxblood-deep`, receding with slight perspective. Three depth layers drifting
at different rates on scroll. A central arch, taller, with a brass keystone.

**Motion, ~20s cycle:** two brass lines enter from the left and right edges,
travel inward at *slightly different speeds* — they are not symmetrical, because
two people are not — and meet beneath the central keystone. The keystone warms,
a brief bloom no more than 4% opacity above ambient. The lines settle and rest.

**Two paths converging under a doorway.**

Typography carries the emotion: Fraunces at display size, #1 The Arrival at 70ms
stagger. Ambient warmth from #14 The Candlelight, 20s cycle, 4% swing.

**Constraints:** pure SVG plus CSS, no canvas, no WebGL, no animation library.
Under 25kb including the SVG. Transform and opacity only. Full stop under
`prefers-reduced-motion` — renders in its final state. No layout shift. New
keyframes registered as marketing-only. No new colour tokens. **No star shapes,
no constellations, no celestial motifs of any kind.**

At 375px: three arches and one keystone. The convergence still reads. Never a
horizontal scrollbar.

---

## 9. PART H — PREMIUM UI AND MOBILE

**H1. Verify and refine what exists:** bottom nav, collapsing top bar,
pull-to-refresh, skeleton loading, offline page, PWA install, scroll restoration,
back-button contract, long-press.

**H2. Add:** floating action button (#7 The Press), sheets not pages for anything
secondary (#8 The Settle), view transitions between match card and match view
(#9 The Morph), safe-area insets on every fixed element, haptics at exactly three
moments (unseal, guardian approval received, mutual interest), 44px minimum touch
targets, optimistic UI for approve/decline with rollback — never for credits.

**H3. Premium pass:** generous whitespace, real typographic hierarchy, every
empty state written and designed, every loading state at three tiers, every error
state in the product voice, consistent 8px rhythm, no shadows anywhere.

Measure at 375, 834 and 1440 using `HorizontalOverflowTest`'s existing iframe
harness. **Do not write a new harness** — headless Chrome clamps its viewport at
500px and a naive one lies.

**Real-device testing cannot be done by the agent.** Do the measurable half and
hand over a specific walk-list.

---

## 10. PART I — FAQ, SUPPORT AND HELP

FAQ page, accordion, all strings in the copy deck, answers describing outcomes
not mechanisms. Support ticket form for members and guardians reaching an admin
inbox. Ticket types include "my trusted contact is not responding fairly".
Admin replies inside the platform, threaded, logged. **The relay prohibition is
rendered in the interface**, above the compose box.

---

## 11. PART J — GENDER-SEPARATED ADMIN ✅ DONE

Two roles: `reviewer_female`, `reviewer_male`. Enforced at the query layer, not
the view. 404 never 403. Super admin retains full access; every super-admin
photograph view is logged. Positive control: the matching reviewer must get
through, or every 404 assertion passes against a dead route.

---

## 12. ORDER OF WORK

1. Part A ✅
2. Part J ✅
3. Part B
4. Part C
5. Part E
6. Part D
7. Part F
8. Part G
9. Part H
10. Part I
11. Full suite, forbidden-terms reader over rendered output, breakpoint audit
    regenerated, `PositiveControlsAreAliveTest` green

---

## 13. ACCEPTANCE

- No affinity component anywhere; forbidden-terms reader green over rendered output with an empty allow-list
- Guardian system works in all four requirement settings
- An appointed guardian can never receive a photograph, proven with a positive control
- No government ID collected or stored anywhere
- AI cannot reject a member; auto-rejection is not expressible
- Profile writer cannot introduce a fact absent from the input, proven by fidelity test
- Both AI features degrade cleanly with no API key
- A reviewer cannot reach the opposite gender's photographs by any route
- No public page describes any mechanism
- Banner contains no photograph of any person, is under 25kb, stops completely under reduced motion
- Every changed screen measured at 375, 834, 1440 with no horizontal scroll
- All existing guards green; every new guard carries positive controls both ways

---

## 14. ONE OPEN CONFLICT — FLAG, DO NOT RESOLVE

The client does not want the trusted-contact system presented to men and believes
many members will not have one. The developer requires it for compliance.

§B1 makes this a setting rather than a code path, which is why it is buildable
today. **Do not resolve the policy question in code and do not pick a default
that looks like a decision.** Ship with `guardian_requirement` unset and surface
it clearly in settings so the choice is made deliberately, by a person, in the open.
