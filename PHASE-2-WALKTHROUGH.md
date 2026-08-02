# RealVows — Phase 2, in plain words

**For Umar.** No technical knowledge assumed. Live at **https://realvows.skillleo.com**

Phase 1 gave you sign-up, profiles, photographs and verification. Phase 2 is the part
that makes RealVows a business: the matching, the sealed introductions, and taking money.

---

## The one-sentence version

A member joins free, builds their profile, gets verified by a person — and then
**immediately sees people waiting for them, sealed.** They pay to open them.

That inversion is the whole engine. In Phase 1 nothing happened after verification.
Now, the moment you approve somebody, the system finds their matches and puts them on
the dashboard behind a brass seal. There is something to buy before they have had to
think about buying anything.

---

## Six accounts, six states — click these in this order

Every state is reachable from the sign-in panel in one click. No buying, no waiting.
Password for all of them is `realvows2026`.

| Click | What you will see | Why it matters |
|---|---|---|
| **Girl 1** | Verified, **no credits, sealed introductions** | The commercial hook. This is the screen the business rests on |
| **Girl 2** | Verified, **credits in hand, nothing opened** | The moment before the ceremony |
| **Girl 3** | Verified, **three opened**, full profiles, receipts | The payoff — what they paid for |
| **Girl 4** | **Awaiting verification** | What a new member sees while they wait |
| **Girl 5** | Verified, **nobody good enough yet** | The honest empty state |
| **Boy 1** | Mirrors Girl 1 | The same thing from a man's side |
| **Admin** | Everything | Queue, match log, payments, credits, reports, settings |
| **Reviewer** | Queue and reports only | What a person you hire later can reach |

---

## What happens, step by step

### 1. You approve a profile

Nothing looks different to you — same queue, same two buttons.

Behind it, the system goes and finds that person's matches. It takes a minute or so
(the server batches this work), and then their introductions are waiting.

### 2. They see people waiting

Their dashboard says, for example, **"Eight people are waiting to meet you."**

Each card shows a first name and age, a city, **the compatibility score at full
strength**, one or two sentences explaining why — and a **pressed brass seal** sitting
on the lower edge where wax sits on an envelope.

The seal *is* the locked state. There is no "Locked" label, because a card wearing an
intact seal does not need one.

What it does **not** show: any photograph, the surname, or the profile.

**The photographs are genuinely not there.** This matters and it is worth being precise.
We did not blur them or hide them with styling — anyone who knows how to open a
browser's developer tools undoes that in four seconds and has the full-size photograph
of a real member. Instead the sealed card contains a decorative panel and **the server
never sends the picture at all.** There is nothing to uncover because nothing was sent.

### 3. They pay

Three packages, on `/packages`:

| | Introductions | Price |
|---|---|---|
| Single | 1 | $20 |
| Triple | 3 | $50 |
| Octet | 8 | $100 |

**You can change these yourself** in Settings → Packages, with no help from us and no
deploy. Old receipts keep showing what was actually paid at the time.

Checkout is one screen: the package, the price, one button. No upselling. Somebody about
to spend money on finding a husband should not be handled like a supermarket checkout.

### 4. The unsealing

This is the moment of the product, and it is worth showing people.

Payment goes through and the sealed cards **open one at a time**. The brass seal
**splits along a hairline and its two halves part**, a shimmer sweeps across, and the
photograph appears underneath — as though the light is doing the revealing. The card
settles. A moment later the next one begins.

About a second per card. Three cards is about three and a half seconds. At the end, one
line arrives: *"They're yours to meet."*

If somebody has asked their phone to reduce animation — many people do — they get a calm
fade instead and still see everything.

**So you are not worried:** the animation is decoration. The credits are spent and the
introductions are open in the database *before* it starts. If a member closes the tab
halfway through they lose nothing.

### 5. They open an introduction

The face first and alone — a full-width portrait with their name over it — then the
other three photographs, the score, and the part people will talk about:

> **What you have in common**
> *You both put family first in your values.*
> *You're both hoping to marry within the year.*
> *You both speak Urdu and English.*
> *Neither of you smokes.*

Every line is drawn from what **both people actually typed about themselves**. Nothing is
invented. That is what makes the score feel earned, and it is the thing that makes people
ask *"how does it know that?"*

Their whole profile follows, in the same order as the seven steps they filled in. **A
question somebody chose not to answer is simply absent** — never an empty label, because
a profile with gaps must read as a person, not a broken page.

---

## How the matching actually decides

Six things are weighed. Five are what you would expect: what they each asked for, their
values, lifestyle, personality, and physical preferences.

The sixth is the internal one we discussed. **It is invisible.** It is not named anywhere
a member can reach — not on a screen, not in an email, not in the page's hidden code, not
in a file name. The system is built so a future developer *cannot* accidentally reveal
it: the part that writes the "why we chose them" sentences is never given that score, so
it has nothing to say about it even if somebody tried.

An automatic check runs on every change and refuses the work if any of that language
appears. It tests every sentence the system can produce.

**Two details that make it fairer than the obvious version:**

*Ranking counts.* Two people who both put family **first** are a far better match than
two who both put it fifth.

*Difference is sometimes good.* On most things similar is better — but two people who are
both wildly spontaneous have nobody booking the flights, so on planning a moderate
difference scores higher than being identical.

**Incomplete profiles are not punished twice.** A blank answer is set aside and the rest
weighted up, rather than scored as "incompatible".

Nobody below **65 out of 100** is ever introduced. If nobody clears it:

> *"We haven't found someone we'd stand behind yet. We'd rather send you nobody than send
> you the wrong person."*

You can adjust that 65 and the batch size in Settings → Matching. The six weightings are
shown there but deliberately cannot be edited — they define what RealVows means by a good
match, and a stray click should not change that.

---

## Things that protect the member

**A credit comes back automatically.** If somebody you approved is later suspended or
loses their verification, everyone who *paid* to open them gets their credit returned and
a calm email — without asking, and without being told why the other person went. They
paid for an introduction to a verified member; if we can't stand behind that person, they
shouldn't be out of pocket.

**Sealed introductions expire after 90 days; opened ones never do.** A three-month-old
match was scored against a platform that has changed. Opened ones were paid for and are
permanent.

**Anyone can report a concern.** A quiet action on the match view, never a loud red
button — this is a marriage platform, not a moderation product. It lands in a queue
beside your verification queue. **The person reported is never told anything**, at any
stage. A system that leaks back to its subject invites retaliation against somebody who
did the right thing.

---

## Your screens

| Where | For |
|---|---|
| **Admin → Queue** | Verifications, with Reports as a tab beside them |
| **Admin → Match log** | Every introduction with the full working. The only place the six components are visible |
| **Admin → Payments** | Every transaction; ask the gateway directly what happened to any one |
| **Admin → Credits** | Give or take introductions, with a reason kept forever |
| **Settings → Packages** | Your prices and package names |
| **Settings → Matching** | The score threshold and batch size |
| **Settings → Email** | Your mail credentials, changeable any time |

---

## About the money

**The payment system is finished and working — against the sandbox.**

Your live merchant account needs a registered business name, then a bank account, then
underwriting. That is weeks and cannot be hurried. Everything is built and tested against
PowerTranz's test system.

**When your account is ready, going live is you typing your credentials into Settings →
Payments.** Not a rebuild, not a deploy, not an invoice. Until then a brass banner sits
across the payments page so nobody mistakes a test for a real transaction.

Three things are handled that cost platforms real money:

**Nobody can be charged twice.** Payment gateways retry — that is normal. The database
physically refuses to record the same payment twice. It is not that the code remembers to
check; the second one *cannot be written*.

**Nobody can open the same introduction twice.** Two tabs, one credit, both clicked at
once: exactly one opens, and the other is told so and reassured they were not charged.

**A lost payment is found.** If the bank's confirmation never reaches us — it happens — a
job runs hourly, notices, asks the gateway what really happened, and finishes the job.

---

## On a phone

Add it to your home screen and it opens with no browser bar — indistinguishable from an
app you downloaded. Scroll position survives opening a match and pressing back. The
status bar colour follows the screen. There is a designed offline page.

**Photographs and private pages are never stored on the device**, so nothing personal is
left behind on a shared or lost phone.

Every screen has been measured at **375px, 834px and 1440px** — 33 page and width
combinations, all confirmed free of sideways scrolling.

---

## ⚠️ Checks that still need a real phone

These cannot be automated and are genuinely outstanding:

1. **Add to home screen** and look at the launcher icon — the maskable crop fails silently
2. **The unsealing on a mid-range Android**, not a flagship
3. **The keyboard not covering** the sticky action bar when typing
4. **Pull to refresh** on the dashboard
5. **Haptics** on tab change, unlock, and the unsealing finishing

---

## What Phase 2 is not

- **Match emails and WhatsApp** — Phase 3
- **"I'm interested" / "pass", and swapping contact details** — Phase 3
- **Phone verification actually sending a code** — Phase 3
- **Real legal wording** — the three pages are still `[DRAFT]` and need a lawyer
- **The demonstration members** — all thirty are invented, with stock photographs, and
  must be removed before real members join

---

## Before you launch

1. **Mail password** — Settings → Email, then Test connection
2. **The one-minute cron** — added in hPanel; confirm it is firing via View Output
3. **PowerTranz credentials** when the merchant account clears
4. **The three legal pages**, written properly
5. **Photography licences** — `public/images/site/CREDITS.md` lists six images whose
   source is unrecorded. Almost certainly fine, but "almost certainly" is not a licence
6. Say the word and I will remove the demonstration members and close the one-click
   sign-in

---

*SkillLeo SMC Pvt Ltd · Agreement SKL-2026-RV-001 · Phase 2*
