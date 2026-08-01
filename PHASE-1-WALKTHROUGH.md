# RealVows — Phase 1 Walkthrough

**Live at: https://realvows.skillleo.com**

Everything below is built, deployed and working right now. Open the link, click
a button on the login page, and you are inside — no password to type.

> **This is a review build.** The 30 members in it are invented and their
> photographs are demonstration images. A brass banner sits at the top of every
> page saying so, and it disappears the moment this becomes the real platform.

---

## 1. How to get in — 15 seconds

Go to **https://realvows.skillleo.com/login**

Under the login form there is a panel headed **"Demo · local only"** with
buttons. One click signs you straight in.

| Button | Who you become | Where it drops you |
|---|---|---|
| **Admin** | Umar — the owner | The verification queue |
| **Reviewer** | A staff member hired to check photos | The verification queue |
| **Boy 1–6** | A male member | Their dashboard |
| **Girl 1–6** | A female member | Their dashboard |

Every account also works with a normal password if you prefer:
`boy1@realvows.test` … `girl15@realvows.test`, password **`realvows2026`**.

**Suggested order for showing Umar:** Home page → Girl 1 (member experience) →
Admin (the business side). It takes about six minutes.

---

## 2. There are three kinds of people, and they see different things

This matters, because it is a real business rule and not just a menu.

| Role | Can see | Cannot see |
|---|---|---|
| **Member** | Their own profile, their own matches, their own purchases | Anything belonging to anyone else |
| **Reviewer** (`admin`) | The verification queue, member lookup | **Settings — deliberately.** Someone hired to check photographs has no business holding the payment gateway keys |
| **Owner** (`super_admin`) | Everything, including settings and the audit log | — |

**Try this:** sign in as **Reviewer**, then type
`realvows.skillleo.com/admin/settings` into the address bar.
You get a **404 — page not found.**

Not "access denied". A 404. Because "access denied" would confirm the page
exists and tell someone exactly where to keep pushing. As far as that account
is concerned, the page simply is not there.

Same thing happens if a **member** tries any `/admin` address.

---

## 3. The public site — what a stranger sees

### Home — https://realvows.skillleo.com

- **Split hero** with two couples. One is visibly Muslim, deliberately — Umar
  asked for this so Muslim members can see at a glance the platform is for
  them. It is a market decision, not decoration.
- **"For those who mean it"** — a quiet italic line above the headline, the way
  a wedding invitation speaks before it names anyone
- **A single promise, given its own band**: *"Nobody here is passing the time."*
- **Trust strip** — Free to join · Verified identity · Four photos · Email and
  WhatsApp
- **How it works** — 01 Build your profile, 02 Get verified, 03 Receive your
  matches
- **"Compatibility, measured properly"** with a score that **counts up from
  zero** as you scroll to it
- **"Sign up is 100% free"** — bold and unmissable, exactly as Umar asked

**Things to point out:** scroll slowly. Sections fade up as they arrive, a
brass hairline draws itself under each heading, and a small brass ornament
separates one section from the next. The cream has a faint paper texture on it.

**And notice what is NOT there:** no search box, no "browse profiles", no grid
of members. RealVows chooses matches and delivers them. There is nothing to
browse, anywhere in the product.

### Legal pages
- https://realvows.skillleo.com/terms
- https://realvows.skillleo.com/privacy
- https://realvows.skillleo.com/community-guidelines

All three are real pages, clearly marked `[DRAFT — pending client legal copy]`
so nobody mistakes placeholder wording for final wording.

---

## 4. Joining — https://realvows.skillleo.com/register

Split screen: wedding photography behind a deep oxblood wash on the left,
the form on the right.

**What it collects:** email, password with a show/hide eye inside the field,
confirm password, and a mobile number with the country code sitting flush
against it.

**Three things worth showing Umar:**

1. **The "Not verified yet" pill** beside the mobile number. The slot for phone
   verification is built and visible from day one; it gets connected to
   WhatsApp in Phase 3. Nothing has to be redesigned later.
2. **Type a short password** and submit. The error is calm and rose-coloured,
   slides gently into place, and says exactly what to fix. No red flash.
3. **The 18+ tick box is not the age check.** It records a declaration. The
   real check happens on our server against the date of birth, later. A tick
   box proves nothing — anybody can tick it.

Attempts are limited to **5 per minute** per address and per network, so nobody
can machine-gun the login page.

---

## 5. The member experience — sign in as **Girl 1**

### The dashboard — /dashboard

Three cards across the top:

| Card | Shows |
|---|---|
| **Profile completeness** | A brass ring at 100% |
| **Verification status** | The brass **Verified** badge and the date approved |
| **Match credits** | The number, very large |

Below that, a designed empty state — *"Your matches will appear here."* — with
a warm line about what happens after verification. Not a blank panel. A member
who has just finished a long profile should not be met with nothing.

### The profile — /profile

**This is the heart of Phase 1.** Seven steps, in Umar's agreed order:

**Account · Personal · Physical · Photos · Lifestyle · Values · Preferences**

On desktop the steps run down the left in a sidebar — the current one in a
filled brass pill, finished ones with a brass tick. On a phone it becomes one
full-screen step at a time that slides sideways.

**Show Umar this:** type something into any field, then **refresh the page
without pressing save.** It is still there.

Everything saves to the server 800ms after you stop typing, and again if you
close the tab. A draft survives a refresh, a closed browser, and moving from
a phone to a laptop — because nothing is held only in the browser. Someone
filling this in over three evenings never loses a word.

**Worth clicking through:**
- **Values step** — pick five values and put them in order with the arrows.
  The order carries meaning: first is what matters most.
- **Personality** — four sliders with a brass thumb (Introvert↔Extrovert,
  Planner↔Spontaneous, and two more)
- **The bio** counts words live and tells you kindly when it is long enough
- Anything with four or fewer answers is a row of buttons, never a dropdown

### Photos — /profile/photos

**Exactly four slots**, never six: Passport style · Full length · Social ·
Your choice.

Below a hairline divider, kept visually separate, is the **verification photo**
with the code shown large — the member writes that code on paper and holds it
up. It is labelled **"Never shown on your profile"**, because it never is.

**What happens behind every upload:**
- We read the file's actual contents to check it is really a photograph — not
  the filename, which anybody can rename
- Resized, and saved as both WebP and JPEG, so it loads about a third faster
  on a phone
- **Location data stripped.** Phones stamp GPS coordinates into photographs by
  default. Publishing a member's home location would be a serious problem, so
  we remove it.
- Stored **outside the public web folder** and served only through a link that
  **expires after an hour** and checks who is asking

**Try this:** open a member photo, copy the image address, and paste it into a
private browsing window. **It will not load.** Even a valid link, forwarded to
someone else, is useless — because we check who is asking, not just whether the
link is correct.

### Packages — /packages
Real prices (US$20 / 1 · US$50 / 3 · US$100 / 8), the free-signup promise
restated, and no buy button — because checkout is Phase 2. An honest "not yet"
beats a dead button in front of the client.

### Account — /account
Change the mobile number or the password.

**Worth mentioning:** changing the mobile number automatically marks it
unverified again. Carrying the old verification across would mean matches going
to whoever now owns that old handset.

---

## 6. The business side — sign in as **Admin**

### The verification queue — /admin/verifications

This is how RealVows stays trustworthy, and it is Umar's own differentiator.

The queue shows **oldest first — deliberately.** Someone who submitted three
days ago has been waiting three days; a newest-first queue would leave them
waiting forever while fresher submissions kept arriving.

Three filters, each carrying its own count:

**Waiting 4 · Verified 24 · Sent back 2**

So a reviewer sees there are four waiting without clicking to find out.

### Reviewing one — click any waiting member

- The **code we issued** is shown very large, next to the verification photo
  and all four profile photos, side by side and big enough to actually compare
  faces. A decision takes seconds, not squinting.
- **Approve** → the member becomes verified and a warm branded email is queued:
  *"You're verified — welcome to RealVows"*
- **Send back** → you pick a reason from a list, add an optional note, and they
  get a kind email telling them exactly what to fix and their code again

Every decision is written to an **audit log** — who decided, what, and when.

**The important part:** a profile that is not verified can never be matched,
never be matched to, and never be shown to anyone. That is enforced in the
database query itself, not just hidden in the interface. Right now: **24 of the
30 members are in the pool. The other 6 are correctly excluded.**

### Settings — /admin/settings

**This is the one Umar will care about most.**

Every service RealVows connects to is editable here, in the browser. He never
opens a file on a server, never needs a developer to change an email password.

Seven groups, each showing how many settings still need filling in:

**General 3 · Email 7 · Photo storage 6 · Verification ✓ · Matching ✓ ·
Payments 3 · WhatsApp 4**

A tick means that group is fully set up.

- **Passwords and keys are encrypted** in the database and only ever shown back
  as `••••••••1234` — the last four characters. The full value never leaves the
  server.
- Leave a masked field untouched and save: **the real password survives.** This
  is tested, because it is the kind of failure that would be silent.
- **"Test connection"** genuinely tries the connection and reports back in
  plain English — not a code
- Every change is logged: who, when, and that it changed — **never what it
  changed to**

---

## 7. Things that are true even though you cannot see them

Worth saying to Umar, because they are what he is actually paying for.

**Nothing is browsable.** No search, no filters, no directory. Built in from
the beginning.

**Photographs are genuinely private.** Not "hard to find" — actually
unreachable without a session, an expiring link, and an ownership check.

**Credit balances are added up, never stored.** When payments arrive in Phase
2, a duplicate message from the payment company cannot double-credit anybody.
That is guaranteed by the database's own structure, not by hoping the code
remembers.

**The matching weights are locked in code**, not in settings, so a stray click
can never change the product.

**Background work runs about every minute.** Shared hosting cannot run
something permanently, so a scheduled task picks up queued work. This is why
nothing in the wording ever promises "instantly" — it says "shortly", or
"usually within a day" for verification. We do not promise what the setup
cannot deliver.

**The matching mechanism is invisible.** Umar's commercial decision was that no
member ever learns how compatibility is calculated. It runs entirely on the
server. Members see a score and a reason written from their own answers.

We did not just remove the wording — **the build now fails automatically if
anyone ever puts it back**, along with checks for "six photos" and for any
copy promising "instantly". Those rules now outlive everybody's memory.

---

## 8. Quality — the numbers

| | |
|---|---|
| Automated tests | **46 passing**, 207 checks, run on every change |
| Home page load | **0.6 KB** of code (budget was 30 KB) |
| App load | **90 KB** (budget was 150 KB) |
| Colour contrast | Every combination passes accessibility standards |
| Private files exposed | **Zero** — verified against the live site |

The tests cover the things that would fail *silently*: a member reaching the
admin area, one member opening another's photograph, a saved password being
wiped, the age check, and a draft surviving a refresh.

---

## 9. What is next

| Phase | What arrives |
|---|---|
| **Phase 1 — done** | Everything above |
| **Phase 2** | The matching engine, locked teaser matches, PowerTranz payments, credits |
| **Phase 3** | Email and WhatsApp delivery, the match inbox, launch |

The database tables for Phase 2 are **already built and empty**, with their
safety rules in place — because those are far cheaper to get right while the
tables have nothing in them.

---

## 10. Before real members ever join

Not needed for this review, but on the record so nothing is forgotten:

1. **Turn off the one-click sign-in.** It closes by itself when the site is
   switched from review to live — that is built in on purpose.
2. **Replace the demonstration people.** The 30 members are invented and their
   photographs are stock images. They cannot ship as real members.
3. **Real legal copy** for the three `[DRAFT]` pages.
4. **Confirm the scheduled task runs every minute** on the hosting plan, or
   emails will be slow.
