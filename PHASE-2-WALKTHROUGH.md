# RealVows — Phase 2, in plain words

**For Umar.** No technical knowledge assumed. Live at **https://realvows.skillleo.com**

Phase 1 gave you sign-up, profiles, photographs and verification. Phase 2 is the part
that makes RealVows a business: the matching, the locked introductions, and taking money.

---

## The one-sentence version

A member joins free, builds their profile, gets verified by a person — and then
**immediately sees people waiting for them, locked.** They pay to open them.

That inversion is the whole engine. In Phase 1 nothing happened after verification.
Now, the moment you approve somebody, the system quietly finds their matches and puts
them on the dashboard behind a veil. There is something to buy before they have had to
think about buying anything.

---

## What happens, step by step

### 1. You approve a profile

Nothing looks different to you — same queue, same two buttons.

Behind it, the system now goes and finds that person's matches. It takes a minute or so
(the server batches this work), and then their introductions are waiting.

### 2. They see people waiting

Their dashboard says, for example, **"Eight people are waiting to meet you."**

Each card shows:

- First name and age — *Zainab, 27*
- Their city
- **The compatibility score, at full strength** — a big number with a brass ring drawing
  itself around it
- One or two sentences explaining why
- A brass **Locked** badge

What it does *not* show: any photograph, the surname, or the profile.

**The photographs are genuinely not there.** This matters and it is worth being precise.
We did not blur them or hide them with styling — anybody who knows how to open a browser's
developer tools can undo that in about four seconds, and they would have the full-size
photograph of a real member. Instead the locked card contains a decorative panel and the
server never sends the picture at all. There is nothing to uncover because nothing was sent.

### 3. They pay

Three packages, on `/packages`:

| | Introductions | Price |
|---|---|---|
| Single | 1 | $20 |
| Triple | 3 | $50 |
| Octet | 8 | $100 |

**You can change these prices yourself** in Settings → Packages, without asking us and
without a deploy. Old receipts keep showing what was actually paid at the time.

Checkout is one screen: the package, the price, one button. No upselling, nothing else on
the page. Somebody about to spend money on finding a husband should not be handled like a
supermarket checkout.

### 4. The unsealing

This is the moment of the product, and it is worth showing people.

Payment goes through, and the veiled cards **open one at a time**. A brass shimmer sweeps
across each card, and as it passes, the photograph appears underneath it — as though the
light itself is doing the revealing. The card settles. A moment later the next one begins.

About a second per card. Three cards is about three and a half seconds. At the end, one
line arrives: *"They're yours to meet."*

If somebody has asked their phone to reduce animation (many people do), they get a calm
fade instead, and they still see everything. Nothing is lost.

**Important, so you are not worried:** the animation is decoration. The credits are spent
and the introductions are open in the database *before* it starts. If a member closes the
tab halfway through, they lose nothing at all — they come back to open cards.

### 5. They open an introduction

Full name, all four photographs, their profile, and the part people will talk about:

> **What you have in common**
> *You both put family first in your values.*
> *You're both hoping to marry within the year.*
> *You both speak Urdu and English.*
> *Neither of you smokes.*

Every one of those is drawn from what **both people actually typed about themselves**.
Nothing is invented. That is what makes the score feel earned rather than mystical, and it
is the thing that makes people say *"how does it know that?"*

---

## How the matching actually decides

Six things are weighed. Five of them are exactly what you would expect:

- **What they each asked for** — age, height, distance, and whether the feeling is mutual
- **Values** — what they ranked most important, when they want to marry, children, faith
- **Lifestyle** — smoking, drinking, education, languages, pets
- **Personality** — shared interests, and how they are each wired
- **Physical** — whether they each fit what the other described

And the sixth is the internal one we discussed. **It is invisible.** It is not named
anywhere a member can reach — not on a screen, not in an email, not in the page's hidden
code, not even in the file names. The system is built so that a future developer *cannot*
accidentally reveal it: the part of the code that writes those "why we chose them"
sentences is never given that score in the first place, so it has nothing to say about it
even if somebody tried.

There is an automatic check that runs on every single change and refuses to let the work
through if any of that language appears. It tested a thousand generated sentences.

**Two details that make the matching fairer than the obvious version:**

*Ranking counts.* Two people who both put family **first** are a far better match than two
who both put it fifth. A simpler system would score those identically.

*Difference is sometimes good.* On most things, similar is better. But two people who are
both wildly spontaneous have nobody booking the flights — so on planning, a moderate
difference actually scores higher than being identical.

**Incomplete profiles are not punished twice.** If somebody has not filled in their
lifestyle answers, that part is not scored as zero — it is set aside and the rest is
weighted up. Scoring a blank as "incompatible" would be unfair and would make the whole
platform look broken to anyone still filling in their profile.

Nobody below **65 out of 100** is ever introduced. If nobody clears that bar, the member
sees a designed message:

> *"We haven't found someone we'd stand behind yet. We'd rather send you nobody than send
> you the wrong person."*

You can adjust that 65, and how many people each member is shown, in Settings → Matching.
The six weightings are shown there too, but deliberately cannot be edited — they define
what RealVows means by a good match, and a stray click should not be able to change that.

---

## Your new screens

| Where | What it is for |
|---|---|
| **Admin → Match log** | Every introduction ever generated, with the full working behind each score. The only place the six components are visible. |
| **Admin → Payments** | Every transaction. You can ask the gateway directly what happened to any one of them, and void where possible. |
| **Admin → Credits** | Give or take introductions from a member, with a reason that is kept forever. |
| **Settings → Packages** | Your prices and package names. |
| **Settings → Matching** | The score threshold and batch size. |

---

## About the money

**The payment system is finished and working — against the sandbox.**

Your live merchant account needs a registered business name, then a bank account, then
underwriting. That is weeks, and it is not something we can hurry. So everything is built
and tested against PowerTranz's test system.

**When your account is ready, going live is you typing your credentials into Settings →
Payments.** Not a rebuild, not a deploy, not an invoice. One screen. Until then, a loud
brass banner sits across the payments page so nobody can mistake a test for a real
transaction.

Three things I want you to know are handled, because they are the ones that cost platforms
real money:

**Nobody can be charged twice.** Payment gateways retry — that is normal, not a fault. The
database physically refuses to record the same payment twice. It is not that the code
remembers to check; it is that the second one cannot be written.

**Nobody can open the same introduction twice.** Two browser tabs, one credit, both
clicked at the same instant: exactly one opens, and the other gets a message saying so and
confirming they were not charged.

**A payment that gets lost is found.** If the bank's confirmation never reaches us — which
happens — a job runs every hour, notices, asks the gateway what really happened, and
finishes the job. Without that, the first you would hear is an angry email.

---

## Installing it on a phone

Open the site on a phone, and it can be added to the home screen. From then on it opens
with no browser bar at all — it looks and behaves like an app that was downloaded.

There is an offline page, so losing signal shows something calm and branded rather than
the browser's error screen. Members' photographs and private pages are **never** stored on
the device, so nothing personal is left behind on a shared or lost phone.

---

## What Phase 2 is not

Being explicit so nothing is a surprise:

- **Match emails and WhatsApp** — Phase 3
- **"I'm interested" / "pass", and swapping contact details on mutual interest** — Phase 3
- **Phone verification actually sending a code** — Phase 3
- **Real legal wording** — the three legal pages are still marked `[DRAFT]` and need a
  lawyer, not a developer
- **The demonstration members** — all thirty are invented, with stock photographs, and must
  be removed before real members join

---

## Before you launch

1. Send me your PowerTranz credentials when the account clears
2. Get the three legal pages written properly
3. Say the word and I will remove the demonstration members and turn off the one-click
   sign-in buttons

Everything else is ready.

---

*SkillLeo SMC Pvt Ltd · Agreement SKL-2026-RV-001 · Phase 2*
