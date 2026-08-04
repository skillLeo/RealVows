# What the delivery ledger can honestly say about email

**Short answer: on the current hosting, the fallback chain cannot detect email
failure.** A message that hard-bounces to a dead address is indistinguishable
from one that was read. This document says what that costs, and what it would
take to fix.

---

## 1 · What we actually know

| Channel | What the provider tells us | Highest honest status |
|---|---|---|
| **WhatsApp** | `sent`, `delivered`, `read`, `failed`, with error codes, by webhook | `read` |
| **Email (Hostinger SMTP)** | A `250 OK` at handover. Nothing afterwards. | `accepted` |

That asymmetry is why `MessageDelivery` has a per-channel **ceiling**. An email
row cannot climb past `accepted`, and the admin panel labels it
*"Handed to the mail server"* — never *"Sent"*, and never *"Delivered"*.

⚠️ **A `250` is not a delivery receipt.** It means the next mail server agreed
to take the message. Everything that decides whether a human sees it happens
afterwards and out of our sight:

- the recipient's server rejects it after accepting it (an **asynchronous
  bounce** — the normal shape of a hard bounce)
- it is accepted and filed in spam
- the mailbox is full, or the address never existed
- it is accepted and silently discarded, which large providers do to mail from
  domains with no reputation

**All four are recorded as `accepted` and look identical to success.**

---

## 2 · Why this matters more than it sounds

The email channel is not a nice-to-have. It is **the floor beneath WhatsApp** —
the thing that catches a member whose number is wrong, off WhatsApp, or blocked.

So the fallback chain currently reads:

> WhatsApp failed → send an email → **assume it worked**

The second step cannot fail visibly. A member with a mistyped email address who
also has a bad phone number now silently misses an introduction **they paid
for**, and every screen we have says both messages went out.

⚠️ **This is the vacuous-assertion pattern at the infrastructure level.** Same
shape as the six code instances: something reports success while checking
nothing. The difference is that the thing lying is an admin screen the client
uses to decide whether a member was told.

The `uncertainty()` sentence on every accepted email row exists so the panel
admits this rather than showing green.

---

## 3 · What it would take to know

### Option A — a sending provider with a delivery webhook · **recommended**

Postmark, SES, Mailgun, Resend. All post back `delivered`, `bounced`,
`complained` (spam report) and `opened` events.

| | |
|---|---|
| **Cost** | Postmark ~$15/mo for 10k; SES ~$0.10 per 1,000; Resend free to 3k/mo |
| **Work** | ~half a day. One webhook endpoint — the WhatsApp one is the template, including the signature check — plus a settings group and `applyStatus()` calls. The `CEILING` constant is what changes. |
| **Also fixes** | Shared-hosting IP reputation, which is the larger deliverability problem. Hostinger's outbound IPs are shared with every other site on the box, and we have no control over what they send. |
| **Gives us** | Real bounce detection, spam-complaint handling, and DKIM signing on our own domain |

**This is the one I would take.** The webhook shape is already built and proven
once; the marginal work is small; and it fixes the reputation problem at the
same time, which matters most in the first few hundred sends.

### Option B — a polled bounce mailbox

Set `Return-Path` to `bounces@…`, then poll that mailbox over IMAP on the cron
and parse delivery status notifications.

| | |
|---|---|
| **Cost** | Nothing beyond a mailbox we already have |
| **Work** | ~1–2 days, and the parsing is genuinely unpleasant — DSN formats vary by sender, and soft bounces have to be distinguished from hard ones over time |
| **Detects** | Hard bounces, eventually. Not spam-folder placement, not complaints |
| **Latency** | A cron tick plus however long the remote server takes to bounce, which can be days |

Cheaper in money, more expensive in time, and it answers a narrower question.
Worth it only if a monthly subscription is genuinely unacceptable.

### Option C — leave it, and be honest

What is built today. The ledger says `accepted`, the panel says
*"Handed to the mail server"*, and `uncertainty()` states plainly that we
cannot tell.

⚠️ **This is defensible while the platform has thirty invented members. It stops
being defensible on the day a real member pays for an introduction**, because
that is the day "we cannot tell whether you were told" becomes a refund
conversation rather than a caveat.

---

## 4 · The recommendation

**Move to Option A before launch, not after.** Two reasons, and the second is
the one that decides it:

1. The fallback chain is the safety net under a paid product, and right now
   the net cannot report a hole in itself.
2. **Sender reputation is earned on the first few hundred messages.** Sending
   those from a shared Hostinger IP with no DKIM on our own domain spends the
   reputation before we have it — and reputation, once poor, takes weeks to
   repair. Switching afterwards means starting that clock again.

Not urgent this week. Genuinely urgent before real members arrive.

---

## 5 · What changes in code when it happens

Small, and deliberately so — the shape is already right.

- `MessageDelivery::CEILING['email']` rises from `accepted` to `delivered`.
- A provider webhook calls the existing `applyStatus()`, which already refuses
  to go backwards and already handles repeats.
- `uncertainty()` returns null for the statuses that become facts.
- `FallBackToEmail` gains a real terminal failure to react to, which is what
  makes the *"member must never silently miss an introduction"* promise
  enforceable rather than aspirational.

⚠️ **Do not raise the ceiling without the webhook.** It is one constant, it will
look like a one-line improvement, and it would restore the exact lie this
document exists to prevent.
