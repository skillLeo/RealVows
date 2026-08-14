# R5 — email verification moves to the end

Everything in the brief is done except the one thing it told me not to do yet.

---

## 1. What changed, in order

**Registration no longer sends anything.** `RegisteredUserController` used to fire
`Registered`, which sent the confirmation email before the person had entered a
single answer. It now creates the account and redirects straight to the builder.

**The builder, photographs, the trusted-contact screen and the dashboard came
out of the `verified` middleware group.** Packages, the inbox, matches and
checkout stayed inside it. Somebody who has not confirmed can build; they cannot
transact or be introduced.

**Completing the profile is what asks for the confirmation.** `ProfileBuilderController::submit`
sends it at the moment the last answer is saved, which is the moment the client
described: the person has already done the work, so going to their inbox costs
them nothing.

---

## 2. The check whose meaning changed — reported, not silently adjusted

The brief asked me to audit every `verified` check and report anything whose
meaning changed. **One did, and it is the important one.**

`Profile::scopeMatchable` — the query behind every candidate list — checked
**identity** verification: the reviewed photograph and code. It has never known
anything about email, and it never needed to. The `verified` middleware meant an
unconfirmed member could not reach the builder, so they could not have a profile,
so they could not be matched. The guarantee lived two layers away from the query
that depended on it.

Taking that middleware off would have opened the pool. An unconfirmed member
could have completed a profile, been approved by a reviewer, and been introduced
to somebody — and nothing in the codebase would have objected.

The gate is now **in the query**, at [Profile.php:177](app/Models/Profile.php#L177):
they are absent from the candidate set, not filtered out of its results. A filter
is one forgotten call site from a leak; a row that was never selected cannot leak.

Two more places needed the same treatment, both found by asking "what else did
that middleware silently guarantee?":

- **The review queue.** `submit` holds the verification at its current status
  until the address is confirmed. A reviewer's time is the scarcest thing here
  and every row is a real person's photographs. Her work is still saved.
- **The trusted-contact invitation.** This one reaches somebody who never signed
  up. Naming a father used to require a confirmed address by accident; now it is
  [checked on purpose](app/Http/Controllers/TrustedContactController.php). The
  nomination saves and the token is issued — only the message waits.

---

## 3. Emails carrying the faults — listed, not rewritten

All fifteen read. **Two carry them. Thirteen are clean.**

| Email | Fault |
|---|---|
| `confirm-email.blade.php` | "ten minutes" — a duration. "whole profile" — effort framing. |
| `verification-rejected.blade.php` | "takes about two minutes" — a duration. |

The first is **the email Umar objected to**, and I have not touched it. He said
earlier wording was better and did not say which. Guessing costs a revision and
reads as not listening.

The second I found while reading the rest. The brief says to list rather than
rewrite so he can take them one at a time, so it is listed.

Both are recorded in [`NoDurationsOrEffortLanguageTest`](tests/Feature/NoDurationsOrEffortLanguageTest.php)
as `AWAITING_REWRITE`, with the reason each is held. **They are listed rather than
excluded from the sweep** — an exclusion would make the debt invisible and the
guard would go green over the exact fault it was written to catch. A further
assertion fails if an entry stops matching, so a dead exemption cannot sit there
handing a free pass to whatever file lands at that path next.

The guard covers the copy deck, the public pages, every email, the auth screens
and the builder steps.

**The seven-step indicator inside the builder stays**, and there is a test
asserting it stays. "Step 3 of 7" while somebody is already inside the form is
orientation. "Seven steps" before they begin is a promise about effort. Same
numbers, opposite effect, and only the second is banned.

---

## 4. Retention — and the thing that enforces it

Moving verification created a category of data that did not exist before.

An account that never confirmed used to hold an email and a password hash, and
could never hold more, because the builder was behind the middleware. Now it can
hold four photographs of a face, a date of birth, and **a trusted contact's
mobile number**. That contact never signed up, agreed to nothing, cannot ask us
for a copy and does not know the record exists. Not keeping it forever is the
only protection he has.

The policy now says **twelve months**, and `"a short period afterwards"` — which
is not a retention period — is now **thirty days**.

Before writing either number I wrote the thing that keeps it:
[`realvows:forget-abandoned`](app/Console/Commands/ForgetAbandonedRegistrations.php),
scheduled weekly. A number in a privacy policy with nothing carrying it out is a
sentence, not a promise.

Two things it gets right that are easy to get wrong:

- **It force-deletes.** `User` soft-deletes, so an ordinary `delete()` would set
  `deleted_at` and leave the date of birth and — through the cascade — the
  contact's phone number sitting in the table indefinitely, under a policy
  sentence saying we removed it. The account would vanish from every screen,
  which is exactly what would have made it impossible to notice.
- **Files before rows.** Deleting the row first orphans the photographs: a face
  on a server no record mentions, which no audit finds and no request to be
  forgotten can reach.

It **reports by default and only deletes with `--apply`**, which the scheduler
passes and a human does not. Its tests lean on what it must *not* touch — a
confirmed member of two years' standing, somebody who registered last week, and
an operator account (which also carries a null timestamp and would look identical
to a query reading only that column).

One test asserts the policy's number and the command's default are the same,
because nothing else connects two numbers living in different files.

---

## 5. Measured at 375 / 834 / 1440

`node scripts/measure-viewports.mjs` — real Chrome over the DevTools protocol, no
new dependency. **15 of 15 page/width combinations measured. No sideways scroll
anywhere.**

It found two things:

1. **Three 24px buttons in the profile builder** that remove a chosen interest.
   Fixed — the chip is now 44px tall and the button's own hit area is 44px, not a
   pseudo-element, so automated checks can see the fix rather than keep reporting
   it. Taller and not wider on purpose: chips sit 8px apart, so a wide target
   would reach into the next chip's label and a tap meant for one would delete
   another.

2. **Three links on the register page at 20px** — "Terms of Service", "Privacy
   Policy", "Log in". **Not fixed, deliberately.** All three sit inside sentences,
   which WCAG 2.5.8 exempts in as many words. Padding a prose link to 44px puts
   its hit area over the lines above and below, so a tap aimed at ordinary text
   opens a page — a real mis-tap introduced to silence a reported one. The script
   now encodes that exception and prints how many it excused.

The exemption is itself proved before any finding is believed: the script injects
a standalone 20px button and a 20px link inside a sentence, and stops the run
unless the first is caught and the second excused. An exemption written slightly
too wide would excuse everything, and a run that excuses everything prints the
same clean page as a site with nothing wrong.

---

## 6. Still open

**Blocked on Umar:** the screenshot of the confirmation-email wording he
preferred. Nothing else in R5 depends on it.

**His decision, when he is ready:** `verification-rejected.blade.php`, and
whichever of the two emails he wants taken first.

**Not mine to finish:** the bank, for payments and the billing legal entity.

813 tests, 804 passing, 9 skipped.
