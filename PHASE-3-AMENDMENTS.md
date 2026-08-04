# Phase 3 brief — amendments

Changes to the Phase 3 build brief, agreed in flight, recorded here so the
**reasoning** survives the decision. A brief that quietly diverges from the
code teaches the next reader that one of them is lying.

Same convention as the `DO NOT "FIX" THIS` notes in `Billing.php` and
`VerifiedBadge.vue`: the amendment is written where somebody about to undo it
will read it first.

---

## A1 — an unverifiable payload is inert, not rejected

**Brief said:** reject unsigned or mis-signed requests. *"An unverified webhook
is an open endpoint that anyone can post fabricated delivery events to."*

**Built instead:** a **mis-signed** payload is rejected with 403. A payload that
cannot be verified *at all* — because no app secret is configured yet — is
acknowledged with **200 and then dropped**, logged at warning, queueing nothing.

**Why the change.** The brief's threat model is right and is unchanged: nothing
may act on a payload we cannot attribute to Meta. The disagreement is only
about the status code, and it matters because of how Meta responds to one.

Meta **disables a subscription that keeps failing**. Rejecting everything before
the app secret exists means the callback can never be configured in the first
place — and once live, a spell of 403s converts a loud, visible failure into a
silent outage where deliveries simply stop arriving and nothing says so. That
is the opposite of what the brief was reaching for.

So the security property is enforced where it actually bites — **nothing
downstream runs** — rather than at the status code, which is a message to Meta
about our health rather than a security boundary.

**What holds this in place:** `WhatsAppWebhookTest` asserts an unverifiable
payload queues no work, a mis-signed payload is refused, and — the positive
control — a correctly signed payload *does* queue work. Without that third
test, a controller that queued nothing ever would pass the first two.

⚠️ **Do not "harden" this into a rejection.** It reads like a weaker check and
is not one. The stricter-looking version trades a visible failure for an
invisible one.

---

## A2 — variable order is removed as a category, not fixed as a bug

**Brief said:** store the templates' variable order and write a test asserting
the code's order matches the stored definition.

**Built instead:** the test exists, *and* call sites can no longer express an
order at all. They pass a **keyed** array to `WhatsAppTemplates::order()`, which
resolves position from the declaration submitted to Meta.

**Why the change.** The brief's test catches a mismatch after somebody writes
one. This makes the mismatch unwriteable: there is no argument position for a
developer to get wrong, and an unknown or missing key throws by name rather
than sending a message with a blank slot.

The stakes justify the stronger form. A swapped order **sends successfully** —
API returns 200, delivery status reads `delivered`, the admin panel is green —
and the only evidence is the sentence a real person reads on a lock screen.

---

## §4.1 of `PHASE-3-READINESS.md` was wrong

That document claimed Phase 3 would need to split the match status enum into a
second column. **It was already split** — `status` for access and `response`
for the member's reaction, in the Phase 2 reshape migration, annotated
"Phase 3" at the time.

Recorded because the readiness note is the document somebody will plan from.

---

## The last-name defect — and a correction to how it was measured

`MatchPresenter::full()` returned `lastName` on any unlocked introduction. The
schema has said *"private until mutual interest"* since the Phase 2 reshape;
the presenter never caught up. Now narrowed: last name and contact details are
**absent** until both parties independently express interest.

**Blast radius: none.** Confirmed from production data, not from reasoning —
35 accounts, 32 demo-shaped, 3 unlocked introductions, **all three held by demo
accounts**. No non-demo account has ever held an unlocked introduction.

⚠️ **The first version of that audit reported "none" for the wrong reason.** It
filtered on `viewed_at`, which is stamped only by the match *show* page — but
the **dashboard** renders the same payload for every unlocked card and stamps
nothing, and `unlock()` redirects straight to the dashboard. "Unlocked but never
viewed" never meant "never rendered".

The conclusion survived the correction. The first method did not, and would have
produced a confident wrong answer on any dataset where the two differed.

---

## Rule 5 is now enforced by the build

Six instances of "reports success while checking nothing" were caught one at a
time, reactively. The sixth — a zodiac grep that printed `CLEAN` while scanning
zero files — was on the highest-stakes constraint in the product.

`PositiveControlsAreAliveTest` now runs every reader against a violation
planted at test time **and** against a clean sample, because a reader matching
everything is as dead as one matching nothing and passes its own control
perfectly. `Tests\Support\ScannedFiles` fails on an empty file set, so "clean"
and "looked at nothing" can no longer produce the same output.

⚠️ The affinity vocabulary in the two engine files is an **allow-list, not an
ignore-list**: a third file gaining the vocabulary fails the build, and a stale
entry that no longer contains it also fails. It cannot quietly widen.
