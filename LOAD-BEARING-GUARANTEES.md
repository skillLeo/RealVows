# Load-bearing side effects

**The thirteenth failure class**, and a different shape from the twelve before it.

The first ten were *checks that could not see*. The eleventh was *a check that
saw, told the truth, and was not heard*. The twelfth was `MailFake::raw()` — *an
alarm that could not be tested at all*.

This one is:

> **A guarantee held by something that never claimed to hold it.**

Nothing is broken. No check is wrong, no signal is dropped. A property that
everything depends on is true only as a *side effect* of an arrangement nobody
recorded as load-bearing — so the day that arrangement changes for an unrelated
reason, the property stops being true and **nothing objects**.

It does not announce itself, because there is no code to find. The bug is the
absence of a check that was never needed.

---

## The two instances found so far

### 1. The matching pool

`Profile::scopeMatchable` — the query behind every candidate list — checked
**identity** verification: the reviewed photograph and code. It never knew
anything about email.

It never needed to. The `verified` middleware meant an unconfirmed member could
not reach the builder → could not have a profile → could not be matched. **The
guarantee lived two layers away from the query that depended on it.**

R5 took that middleware off for a good product reason. Without the line added at
[Profile.php:177](app/Models/Profile.php#L177), an unconfirmed member could have
completed a profile, been approved by a reviewer, and been introduced to
somebody — reachable for the first time, with nothing in the codebase objecting.

**Proved at its own layer.** `UnverifiedCanBuildButNotBeMatchedTest` calls
`Profile::query()->matchable()` directly, with no HTTP and no middleware. A test
that went through a route would have passed for years on the strength of the
middleware and gone red only after it was removed — which is exactly too late.

### 2. The daily trusted-contact reminder

**Found by looking for the same shape, and introduced by the fix for the first
instance.**

`GuardianService` writes `status => invited` the moment a member names somebody.
That was true the instant it was written, always, because naming a contact
required a confirmed account and the message left in the same request.
`RemindTrustedContacts` reads `status = invited` as *"we have written to this
person"* and chases them daily.

R5 holds that message until the member confirms their own address. **The row
still says `invited`.** So the reminder would have chased a father the platform
had deliberately decided not to write to — defeating the hold entirely, one
morning later, from a file that has nothing to do with it.

Fixed in the query, with both controls: the held invitation is not chased, and a
genuinely invited contact still is.

---

## ⚠️ The known lie left standing

`status = invited` still means *"named"*, not *"invited"*. The reminder no longer
believes it, but the field is dishonest and `GuardianRiskScorer` also reads it.

Not restructured here, deliberately: changing status semantics touches the risk
scorer and the review queue, and doing that in the same pass as a deploy is how a
correct fix becomes an outage. **Recorded rather than quietly left.** The honest
repair is a status that means named-but-not-yet-written-to.

---

## How to sweep for it

The pattern has no signature to grep for, so the sweep is a question asked of
each layer that guards something:

> **If this layer were removed tomorrow for a reason that has nothing to do with
> safety — what silently becomes reachable?**

And for each guarantee found:

> **Is it enforced at the layer that owns it, or inherited from above?**
> **Is there a test that exercises it with the layer above removed?**

A guarantee only ever tested through HTTP is suspect by construction: the test
passes on the strength of the middleware, so it cannot tell you whether the thing
underneath would hold on its own.

### Swept this round

| Layer | What it silently guarantees | Verdict |
|---|---|---|
| `verified` middleware | Pool membership, review queue, contact invitation | **Three found.** All three now enforced in their own query |
| `admin` alias | Every `/admin` route | Clean — 0 admin routes lack it, and each carries a per-route permission on top |
| `guardian` alias | The trusted-contact dashboard | Clean — `GuardianDashboardController` scopes by `seeker_profile_id` in the query, not by the middleware |
| `CandidatePool::query()` | The single door into matching | Clean — one chokepoint, and it calls `->matchable()` |
| `InboxController` | A member seeing only their own introductions | Clean — `where('seeker_profile_id', …)` in both queries |
| `MatchRecord::scopeForSeeker` | Ownership of a match row | Clean — constraint is in the scope |

---

## The rule this earned

**When you remove a layer, do not ask what it was checking. Ask what stopped
being true.**

They are different questions, and only the second one finds this.
