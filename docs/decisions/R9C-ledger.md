# R9-C decisions ledger

Every entry is something that would previously have stopped the round to ask.
The brief authorised proceeding on a stated default provided the decision is
recorded. This file is the record.

## 1 · Two brass alphas snapped rather than named

```
WHAT I HIT      Brass literals at .42 and .35 were neither hairline alpha (.4
                and .2) nor any of the three new named tokens.
WHAT I DID      Snapped both to --hairline (.4).
WHY             §2.1: any remaining stray alpha snaps to the nearest named
                token unless the difference is perceptible at 1x on a 390
                viewport. Checked by eye on a 1px border and a gradient stop.
                Neither is perceptible.
WHAT I WOULD    Do .42 and .35 each deserve a name, or is .4 close enough?
HAVE ASKED
REVERSIBLE      Yes. Two one-line changes if either is wanted back.
```

## 2 · color-mix restricted to token inputs, not to single-hue

```
WHAT I HIT      §2.3 says every color-mix must be a pure alpha reduction of a
                SINGLE token. Twenty-five pre-existing calls blend two tokens
                (brass 8% into card) and are the entire surface-tint system.
WHAT I DID      Enforced that every INPUT must be a token. Did not enforce
                single-token-only.
WHY             §2.3 and §2.1 conflict here: obeying §2.3 literally needs about
                twenty-five new tokens, and §2.1 caps the round at three and
                says a fourth means two things are being conflated. §4 says
                ship the precise subset and ledger the imprecise part. The door
                actually being watched is a LITERAL entering through a function
                the colour reader is blind to, and that door is now shut. A
                tint of a blessed hue against a blessed surface introduces no
                new hue.
WHAT I WOULD    Should the twenty-five surface tints each become a token, or is
HAVE ASKED      "both inputs are tokens" the right line?
REVERSIBLE      Yes. The stricter rule is a few lines in the same test.
```

## 3 · One scrim, replacing my own color-mix from last round

```
WHAT I HIT      Two backdrops did the same job in two different hues: .rv-sheet
                used ink at .55, and .rv-navsheet used the oxblood-deep
                color-mix I wrote in R9-B.
WHAT I DID      Both now use --scrim, derived from --ink at .55.
WHY             §2.1 says a fourth token means two things are being conflated,
                and to merge them. Two backdrops are one job. --ink is where
                the surviving legitimate literal came from, so no hue shifted
                to reach it.
WHAT I WOULD    Should the menu backdrop stay oxblood-tinted rather than ink?
HAVE ASKED
REVERSIBLE      Yes, one line. The visible difference is very small: both are
                near-black at similar weight over the same panel.
```

## 4 · Word boundary changed from \b to a letter-digit edge

```
WHAT I HIT      The celestial reader missed rv-horoscope__ring. An underscore
                is a word character, so \b never fires between "horoscope" and
                "__ring".
WHAT I DID      Replaced \b with (?<![a-z0-9]) ... (?![a-z0-9]) on both the
                term match and the allow-list scrubber.
WHY             §2.5 requires the reader to cover class names, and a class name
                is exactly where a term survives a redesign: not in prose,
                where somebody would read it, but in markup nobody re-reads.
                Caught by my own positive control before shipping.
WHAT I WOULD    None. The control found it and the fix is unambiguous.
HAVE ASKED
REVERSIBLE      Yes, but reverting reopens the hole.
```

## 5 · The Questions page is exempt from the card-punctuation guard

```
WHAT I HIT      The terminal-punctuation guard listed all four public pages.
                Questions is built from native <details> elements and has no
                card descriptions, so the reader asserted against an empty set.
WHAT I DID      Scoped the guard to the three pages that have cards, and added
                a test asserting Questions is still details-based, so it fails
                if that page ever gains cards.
WHY             §4: a guard that claims coverage it does not have is worse than
                a narrower honest one. My own extraction proof caught this
                before it shipped, which is the whole point of that rule.
WHAT I WOULD    Should Questions grow cards to match the other three?
HAVE ASKED
REVERSIBLE      Yes. One entry in a list.
```

## 6 · The benchmark had never run, and the first jank control was wrong

```
WHAT I HIT      frame-benchmark.mjs did not parse. Backticks inside a comment
                INSIDE the PROBE template literal closed the string, so the
                file had been a syntax error since it was written.
WHAT I DID      Escaped them, set the profile from §2.7, and ran it.
WHY             It has been "outstanding for two rounds" partly because nobody
                could run it. A script that cannot parse reports nothing, and
                nothing looks the same as not getting round to it.
WHAT I WOULD    None. It is a plain defect.
HAVE ASKED
REVERSIBLE      Yes, but reverting restores a file that cannot run.
```

## 7 · The first positive control for the benchmark was rejected by itself

```
WHAT I HIT      My injected jank was twelve hundred blurred gradients. The
                control refused the run, saying the harness could not see it.
WHAT I DID      Replaced it with a forced synchronous reflow on scroll.
WHY             The control was right. A CSS filter is composited on the GPU
                and never touches the thread requestAnimationFrame is timed on,
                so the probe correctly saw no change. Reading geometry and
                writing style in a loop is jank the main thread actually feels.
WHAT I WOULD    None. The control caught my mistake, which is what it is for.
HAVE ASKED
REVERSIBLE      Yes.
```

## 8 · The deploy failed once and was retried

```
WHAT I HIT      The deploy failed at "Trust the host": ssh-keyscan from the
                GitHub runner could not reach port 22 after five attempts.
WHAT I DID      Confirmed the host was healthy from here (site 200, SSH fine,
                up 12 days), then retried once. The retry succeeded.
WHY             §4: diagnose, fix, retry once. The failure was before any file
                was touched — the job's own message says "Nothing was changed
                on the server" — so production was never at risk. The same
                transient failure happened in R7 and also cleared on a retry.
WHAT I WOULD    Is the runner-to-VPS path worth hardening, given it has now
HAVE ASKED      failed twice in three deploys?
REVERSIBLE      Not applicable. Nothing was changed until the retry.
```

## 9 · The review-build banner is live on the client's own site

```
WHAT I HIT      Every public page on realvows.com carries a banner reading
                "Review build. This is not the live platform. Every member
                shown here is invented, and the sign-in buttons are open to
                anyone."
WHAT I DID      Left it exactly as it is.
WHY             §7 puts anything outside the four public pages and the listed
                guards out of scope, and this is driven by the demo-mode
                setting rather than by the pages. It is also arguably correct
                right now: the demo sign-in buttons ARE open, so removing the
                banner without closing those would be the dangerous half of the
                change. Reported rather than touched.
WHAT I WOULD    Is the banner meant to be visible to the client while he is
HAVE ASKED      reviewing, or should demo mode be closed first?
REVERSIBLE      Yes, one setting.
```

## Worth doing later, not done here

- The runner-to-VPS ssh-keyscan step has failed on two of the last three
  deploys and cleared on retry both times. A longer backoff or a pinned known
  host would remove the coin toss.
- Eleven other scheduled commands still carry Laravel's 24-hour
  withoutOverlapping default. The two every-minute ones matter most: a crashed
  queue worker means no email and no introduction processed for a day.
- The twenty-five surface tints built from color-mix of two tokens could each
  become named tokens. Ledger entry 2 explains why they were not in this round.
