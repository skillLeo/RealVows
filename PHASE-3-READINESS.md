# RealVows — Phase 3 readiness

**Status: not started, and not to be started.** Phase 3 is gated on the
client's written acceptance of Phase 2. This document exists so that when that
acceptance arrives, day one is spent building rather than discovering.

Nothing here is a request to begin. It is a list of what will be needed, what
is still empty, and — the part that matters most — **what Phase 3 has to change
rather than add to.**

---

## 1 · What I need from you on day one

Ordered by how long they take to arrive, not by how much I need them. The first
three have external waiting times measured in days, so they should be started
before day one rather than on it.

### Blocking, with external lead times

| What | From whom | Lead time | Why it cannot wait |
|---|---|---|---|
| **PowerTranz sandbox credentials** | Your PowerTranz account contact | Days | Phase 2 acceptance itself is not complete without them. Nine tests are written and skipping. |
| **WhatsApp Business number** | A US number, per your last message | Days | Meta's template review is measured in days and cannot start without a verified number. See §3. |
| **Meta Business verification** | Meta, via your business documents | 1–3 weeks if it goes badly | The slowest single item in Phase 3. Nothing about WhatsApp delivery can be tested until it clears. |

### Blocking, but immediate once you decide

| What | Notes |
|---|---|
| **The confirmed billing entity** | The registered company the gateway account sits under. Five settings fields, typed once. |
| **A transactional mail provider** | Postmark, SES, Mailgun — any. Phase 3 sends real email to real people; the current setting is `log`, which writes to a file. |
| **A decision on match email frequency** | Immediate on generation, or a daily digest. This changes the job design, not just a setting, so it is cheaper to answer before I build than after. |
| **Real legal copy** | The three pages are still `[DRAFT]`. A lawyer's wording, not mine. Phase 3 is when real members agree to it. |

### Needed, not blocking

- Whether the demonstration members are deleted or kept behind a flag.
- Who receives admin notifications — currently empty.
- Whether photographs move to object storage now or later (§4.4).

---

## 2 · Settings groups still unfilled

Read from the live settings table. **Empty is correct for all of these today**
— none of them is an oversight, and one of them is empty on purpose and must
stay that way until you confirm it.

### `payments` — 7 of 10 empty

| Key | State | Needs |
|---|---|---|
| `gateway` | `powertranz` | — |
| `environment` | `sandbox` | Stays sandbox until the live account clears. |
| `merchant_id` | **empty** | From the sandbox pack, then again for live. |
| `terminal_id` | **empty** | Same. |
| `api_key` | **empty** | Same. Encrypted at rest. |
| `statement_descriptor` | `REALVOWS` | Already set and already conformant. Must match what PowerTranz is configured to send. |
| `billing_legal_entity` | **empty** | ⚠️ Deliberately has no default. Naming an unconfirmed company is a false statement about who holds a member's money, and this value also appears in the Terms of Service, where a guess would be a contract naming the wrong party. |
| `billing_trading_name` | **empty** | "RealVows". |
| `billing_address` | **empty** | Registered address of the settling company. |
| `billing_support_email` | **empty** | Where a member writes about a charge they do not recognise. |

### `whatsapp` — 4 of 5 empty

| Key | State | Needs |
|---|---|---|
| `provider` | `meta` | — |
| `phone_number_id` | **empty** | Meta issues this once the number is verified. It is **not** the phone number. |
| `business_account_id` | **empty** | The WABA ID from Meta Business Manager. |
| `access_token` | **empty** | A permanent System User token, not the 24-hour development token. |
| `template_name` | **empty** | Cannot be filled until a template is **approved**, not merely submitted. |

### `mail` — 7 of 8 effectively empty

Driver is `log`, which writes mail to a file and sends nothing. Every other
field is empty. Phase 3 is the first time the product emails a real person, so
this group moves from "unused" to "load-bearing" in one step.

`from_address` will need to be on a domain with **SPF, DKIM and DMARC**
records. Without them, match emails land in spam, and a matchmaking email that
lands in spam is indistinguishable from a product that does not work.

### `general` — 3 of 4 empty

`support_email`, `admin_notification_email` and `maintenance_mode` are empty.
The first two become visible to members and to you respectively in Phase 3.

### `storage` — all 6 empty, and that is a decision, not a gap

Driver is `local`: photographs sit on the Hostinger disk. See §4.4 — this is
one of the items Phase 3 may need to **change** rather than extend.

---

## 3 · What Meta requires before a WhatsApp template can be submitted

This is the longest pole in Phase 3 and almost none of it is code. In rough
order:

1. **A Meta Business Manager account**, with the business verified. Verification
   asks for a certificate of incorporation, a utility bill or bank statement
   showing the business name and address, and a website whose domain matches.
   ⚠️ **The business name here and the billing entity from §2 should be the
   same company.** If Meta verifies one entity and card statements show
   another, that is a discrepancy both Meta and the acquirer can act on.

2. **A WhatsApp Business Account (WABA)** inside that Business Manager.

3. **A phone number that has never been used on the consumer WhatsApp app.** If
   the number has ever been registered to normal WhatsApp it must be deleted
   from that account first and left for a period. This is the single commonest
   cause of a stalled setup. A fresh US number avoids it entirely.

4. **A display name** that passes review. It must relate to the business —
   "RealVows" is fine. Names that look like a person, or that promise
   something, get rejected.

5. **Then, and only then, a message template.** For our case it is a
   **UTILITY** template, not MARKETING — it is triggered by the member's own
   action and contains no promotion. Utility templates are reviewed faster and
   are cheaper per message.

   The template will look roughly like:

   > Someone new is waiting for you on RealVows — {{1}} out of 100.
   > Open them whenever you are ready: {{2}}

   Review is typically minutes to 24 hours, but **rejection is common on the
   first attempt**, usually for: variables at the very start or end of the
   message, a variable that could be empty, or wording that reads as marketing.
   Budget for two attempts.

6. **A 24-hour session window rule** that shapes the whole design: outside 24
   hours of a member's last message to us, we may send **only** an approved
   template. This is why the match notification must be a template and cannot
   be free text, and it is not a limitation we can engineer around.

**Timeline in practice:** business verification is the slow part — a week is
normal, three weeks if documents are queried. Template approval is fast once
verification clears. Start step 1 as early as you like; it does not depend on
anything I build.

---

## 4 · What Phase 3 must CHANGE rather than extend

This is the section worth reading twice. Everything above is a form to fill in.
The items below are places where Phase 3 modifies decisions Phase 2 already
made — which means schema changes, guard changes, and tests that will need
their reasoning rewritten rather than their assertions relaxed.

### 4.1 · The match status enum — a genuine schema change

`MatchRecord` today has three statuses: `teaser`, `unlocked`, `expired`.

Phase 3 introduces express-interest, pass, and mutual interest. Those are not
new statuses on the same axis — a match can be *unlocked* **and** *passed*, or
*unlocked* **and** *mutually interested*. Modelling them as more values in the
same column would make "unlocked" and "interested" mutually exclusive, which
they are not.

**This needs a second column, not a longer enum**, plus a migration. Doing it
the cheap way first and correcting later means rewriting rows that represent
real decisions real people made about each other.

### 4.2 · The last-name and contact privacy rule — a guard that must be relaxed precisely

`MatchPresenter` deliberately omits `lastName`, `photos`, `bio`, `interests`
and all contact details from a teaser payload. Not nulled — **absent**, so the
browser cannot even see the shape of what is withheld. `TeaserPhotoLeakTest`
enforces this.

Phase 3's mutual-interest feature exists specifically to reveal contact details
— to exactly two people, at exactly one moment, and never before. That is the
most sensitive change in the entire project.

⚠️ **The failure mode is a relaxed test rather than a considered rule.** The
guard will start failing the moment contact details are added, and the tempting
fix is to loosen the assertion. The correct fix is a *narrower* guard: contact
details are absent unless **both** parties have expressed interest, asserted
from both sides, with a test that proves a one-sided interest reveals nothing.

### 4.3 · Phone verification — currently a column that nothing sets

`users.phone_verified_at` exists and `phoneIsVerified()` reads it, but nothing
ever writes it except the account screen clearing it on change. There is no
code that sends a code.

Phase 3 makes this real, which means an SMS provider and per-send cost. It also
raises a question worth answering before building: **if matches are delivered
by WhatsApp, is a separate SMS verification worth paying for**, or does
delivering the first match to the number prove it just as well? I would want
your view before building the SMS path.

### 4.4 · Photograph storage — a decision Phase 2 deferred

Photographs are on the local Hostinger disk. That is fine for thirty invented
members. It becomes a real risk with real ones:

- Shared hosting disk is not backed up in a way I would rely on for the only
  copy of a member's photographs.
- Members' photographs are the most sensitive data in the product.
- A restore from Hostinger's snapshot is all-or-nothing.

The `storage` settings group exists and is empty, so this is a configuration
change rather than a rewrite — **but only if it is done before there is a large
volume of files to migrate.** It gets more expensive every week it waits.

### 4.5 · The demo build switches — must be turned off, deliberately

`APP_ENV=demo` currently drives one-click sign-in as **any** member, the review
banner, and open access. That is correct for a review build and would be a
complete authentication bypass with real members.

Phase 3's launch checklist must include turning it off **and verifying it is
off from the outside**, the way `WebRootExposureTest` verifies the web root —
not by reading config and trusting it. A one-click login left enabled is
indistinguishable, from the outside, from a working site.

### 4.6 · The job runner — a scheduler that is currently doing less than it looks

Four jobs exist and the scheduler runs `queue:work --stop-when-empty` every
minute. There are **zero `dispatch()` calls in application code** — jobs run
from scheduled commands, not from events.

Phase 3's match emails and WhatsApp messages are event-driven: something
happens, a member should hear about it. That is a genuine change in how work
reaches the queue, and on shared hosting with a one-minute cron the honest
promise is "within a couple of minutes", never "instantly". ⚠️ The CI content
rules already forbid the word *instantly* in member-facing copy for exactly
this reason — that rule was written for Phase 2 and becomes more important, not
less, when messages start arriving.

### 4.7 · The copy deck's marketing exclusions

`CopyDeckTest` allows raw strings in Blade marketing pages and emails, on the
argument that they are edited as whole documents. Phase 3 adds transactional
match emails, which sit between the two: they are letters, but the score line
and the rationale must match the product's wording exactly. Worth deciding at
the time whether those specific fragments move into the deck.

---

## 5 · What is explicitly NOT changing

So that scope stays honest when Phase 3 opens:

- **No browsing, search, filters, or member directory.** Out of scope in every
  phase. This is the product's central promise, not a feature that was deferred.
- The matching algorithm itself. Phase 3 delivers matches, it does not re-score
  them.
- The visual system, the motion catalogue, or the seal.
- The payment flow. Once the sandbox suite is green, going live is a credential
  swap.

---

## 6 · The shortest honest summary

**Three things gate Phase 3, and none of them is code:**

1. The client's written acceptance of Phase 2.
2. PowerTranz sandbox credentials — which also close out Phase 2 acceptance.
3. Meta business verification — start it now regardless, because it is the
   longest wait in the project and depends on nothing I build.

**One thing deserves a decision before day one rather than during it:** whether
photographs move to object storage before real members arrive. It is cheap now
and expensive later, and it is the only item on this list that gets worse
purely by waiting.
