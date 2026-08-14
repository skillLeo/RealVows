# R4 Progress

**This file is the memory between sessions. It is written to be believed, so
everything in it was verified rather than remembered.**

R3-PROGRESS.md was once caught listing shipped work as undone. That is the
reason this file records how each claim was checked, not just that it was.

---

## The through-line of this round

The first ten faults in this project were **a check that could not see**.

R4 found the eleventh, which is a different shape and worse: **a check that saw
perfectly, reported honestly, and was heard by nobody.** CI went red on the day
a fault was introduced, named the exact failing test, and was deployed anyway.
Twice.

Once you name that shape you find it everywhere. This codebase had built a great
many alarms and almost no listeners.

---

## Done, and how it was proved

| What | How it was verified |
|---|---|
| **A man's trusted contact was receiving the woman's photographs** | Fixed at both doors. Reintroduced the bug and watched three tests fail; the mirrored scenario (a man's ward) had never been built |
| **`RouteSmokeTest`** — 58 readable routes opened as an entitled actor, 41 writes | Every 404 must be explained too, and each exemption names the exact status it may answer. Positive control both directions |
| **`Storage::fake('private')` faked a disk that does not exist** | The photograph rule's own positive control had been passing on a stray file in real storage. Deleted the file and watched it fail |
| **Navigation and settings-group completeness**, both directions | Asked from the data towards the interface, the only direction that can see an absence |
| **`photo_visibility` as a setting**, default `never` | Broke the policy in each direction: stuck shut fails the release tests, stuck open fails the withholding tests |
| **Privacy, Terms, Guidelines rewritten and versioned** | The photograph clause is generated from the live setting, so it cannot go stale the way the old one did |
| **A verification code and the method to use it, one line apart in an email** | Both removed; the code is no longer passed to the view at all |
| **Founding-100 proved at 99, 100 and 101** | Real rows, through the scheduler's projection, to the rendered HTML, in both directions |
| **60fps at 4× CPU throttle** | Median 8.3ms, worst 15.0ms against a 16.7ms budget. Throttle verified at exactly 4.0, and the run aborts below 1.5 |
| **`realvows.skillleo.com` off the public internet** | 301 to realvows.com. Prepended to the `.htaccess` rather than replacing it, and `/.env` re-checked afterwards: still 403 |
| **Deploy refuses a commit CI has not passed** | Tested against real history: `f9c2652` refuses, `e6058fe` deploys |
| **22 guardian joint photographs missing on production** | Found by reading a log nobody read. Seeder fixed, checker built and scheduled, live data repaired, the route that had been throwing re-requested and returned 200 |
| **The scheduler was piping every byte to `/dev/null`** | And Laravel voids each command's output on top of that. Both layers fixed |
| **No operational alert could be tested at all** | `MailFake::raw()` is an empty method. Both alerts now send a real, assertable mailable |
| **The credit-ledger alert has now actually landed** | Forced a discrepancy on production, then signed in over IMAP and read the message back. Row restored in a `finally` block and the ledger re-verified intact |
| **Every freshly invited contact scored 75 and landed in HIGH** | Found by reading every score. Narrowed, with a control proving the serious case still scores 50 |
| **The home page demonstrated exactly what a message looks like** | Removed, along with six other mechanism disclosures, and guarded by a test |

**774 tests, 765 passing, 9 skipped.**

---

## ⚠️ Three times a guard caught me writing the same fault it guards against

Worth recording, because it is the strongest evidence that the pattern is a
property of the work rather than of any one person's carelessness.

1. **The frame benchmark's throttle control** span a loop until the wall clock
   read 200ms — which takes 200ms at every throttle rate by construction. It
   reported a ratio of 1.01 and I was one step from writing that CPU throttling
   does not work in this Chrome.

2. **The CI gate**, tested by hand with short SHAs, refused the known-*good*
   commit as well as the known-bad one. Testing only the bad case would have
   shipped something that blocks every deploy while looking correct.

3. **The Sentry safety test** asserted `assertNull` on the DSN. Green on my
   machine, where the key is absent. Red on CI, where `.env.example` supplies an
   empty string. A test about member data leaving the server, depending on my
   laptop, written an hour after documenting that exact shape.

---

## Blocked, and on whom

| Item | Owner | Note |
|---|---|---|
| ~~**One sentence on guardian presence**~~ | ~~The scholar~~ | **CLOSED BY DECISION, not by an answer.** The channel to the question kept returning "ask an AI", and an AI answer to this is not a ruling — which is the whole reason Reading B was parked rather than shipped. Holding a launch for an input that cannot arrive is worse than shipping the narrower behaviour and saying so. Live: a woman's introduction is mediated, a man's is not. The branch stays parked and merges in one line if a ruling ever comes |
| ~~**Forward `info@realvows.com` to a phone**~~ | ~~Hassam~~ | **DONE.** Forwarder active to `hassam.dev.571@gmail.com`. The three RealVows alerts sitting unread in that mailbox were the proof the transport worked and nobody was listening; ten bounces beside them produced `RefuseUndeliverableAddresses` |
| **OpenAI API key** | Hassam | Both AI features are finished and dormant |
| **Sentry DSN** | Hassam | Wired and dormant. Free tier, fifteen minutes |
| **Real-device walk** | Hassam | `DEVICE-WALK-LIST.md`. Nothing has ever been opened on a real phone |
| **Meta business verification** | Umar | Gates every WhatsApp message |
| **The bank** | Umar | Gates payments and the billing legal entity, which still prints `[PENDING]` deliberately |

---

## Open questions that are decisions, not oversights

**Whether a digest is enough, or whether error tracking is wanted.** The daily
report closes the gap within a day; Sentry closes it within seconds. With no
members yet the digest is proportionate. Revisit on the day real members arrive.

**Whether mediation applies to men.** If Reading B is confirmed, the parked
branch routes a man's introduction through his trusted contact too, on the
grounds that he only has one if he chose to name one. Narrowing it to women is
a one-line change.

**The couples rail.** Removed, and it is a client decision rather than a build
one.

---

## The rule this round earned

Before adding an alarm, name its consumer. If you cannot, do not add it — write
down that the condition is unwatched instead.

An honest gap is cheaper than a false sense of coverage, and this project has
now paid for that lesson eleven times.
