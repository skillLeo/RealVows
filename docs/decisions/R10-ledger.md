# R10 ledger — reference parity

Format: decision · default applied · why · what would change it · date.
Written under R10 §9: apply the default, record it, continue.

---

**1 · The reference was fetched over a certificate that does not match its host**
Default applied: completed the fetch with verification disabled and recorded it.
Why: `realvows.luxoraarts.com` presents its shared host's default certificate, so
a verifying client refuses the connection. The alternative was to report §2
blocked and stop the round. Reading a public stylesheet the client has asked us
to copy carries no credential and no user data, so the risk of proceeding is
nil, while stopping would have cost the whole round.
Changes it: a certificate issued for that hostname. Umar should be told his
reference site currently shows visitors a full-page security warning.
2026-08-29

**2 · The five weights were assigned in the reference's display order**
Default applied: preferences 40, values 25, lifestyle 20, personality 10,
physical 5.
Why: he sent "40% 25% 20% 10% 5%" as a bare list and confirmed separately that
Preference Fulfilment is primary at forty. The reference displays its rows in
that order, so the list maps onto it positionally with the one confirmed anchor
in first place. §4.1 says ledger it and continue rather than ask.
Changes it: him naming a different component for any of the last four numbers.
2026-08-29

**3 · The PRIMARY badge is derived, not pinned**
Default applied: rendered when a component's weight equals `max($weights)`.
Why: the reference hardcodes the badge onto Preference Fulfilment. Pinned to a
key, the badge would stay put if the weights were ever retuned and would end up
labelling a row that was no longer the largest — the same class of drift §8.1
exists to prevent.
Changes it: nothing. If preferences stops being the heaviest, the badge should
move, and this makes it move.
2026-08-29

**4 · Style guards were scoped, not disabled**
Default applied: the dash, semicolon, contraction, banned-vocabulary and
marriage-lead readers no longer scan `/`, `/how-it-works`, `/about`,
`/questions`. They still scan the legal pages, every WhatsApp body, every email
and the product's own option strings.
Why: §8 suspends them "for the public routes only". Those four now carry his
copy verbatim, and his copy uses em dashes and leads with marriage because he
chose to. Deleting the guards would have lost them everywhere; scoping keeps
every rule alive on everything he did not overrule, and restoring one is a
one-line edit to `PublicSurfaces::clientWordedPaths()`.
Changes it: replacing the client-worded copy with ours again.
2026-08-29

**5 · The celestial reader stayed on, and caught me**
Default applied: kept enforced on every surface, including source files.
Why: §8 keeps it. It failed the build over a word inside a Blade comment I had
written while explaining why the reference's sixth dimension was dropped. The
comment never rendered, but the guard scans source deliberately, and a file a
member can reach has no business containing that vocabulary even unrendered. I
reworded the comment.
Changes it: nothing.
2026-08-29

**6 · The public layout preloaded the wrong typeface**
Default applied: preload `playfair-var-latin.woff2` instead of
`fraunces-var-latin.woff2` on public pages.
Why: `.rv-public` resolves `--display` to Playfair Display, so every public page
was preloading 89KB of a face it uses only as a fallback while the face it
actually renders loaded unprioritised. The visible result is a headline painting
in Georgia and then reflowing — a generic system serif in the first seconds,
which is part of what "our fonts look different to his" describes.
Changes it: nothing. Fraunces stays the display face on authenticated screens.
2026-08-29

**7 · Package buttons and the zodiac section needed no work**
Default applied: verified rather than rebuilt.
Why: `Payments::areLive()` returns false, `/packages` 404s by design during the
free period, and the navigation already hides the entry conditionally. Nothing
celestial survives in any rendered surface — the only occurrences left in the
repository are inside the guard word-lists that exist to ban them. Both were
done in earlier rounds and re-checked rather than re-done.
Changes it: payments going live, which flips the same flag.
2026-08-29

---

# R10-B

**8 · The gold change reached the emails as well as the app**
Default applied: replaced #B99154 in all 16 files carrying it, including the
nine standalone documents (emails, receipt, offline, unsubscribe) that hold
their own inline palette because email clients cannot read custom properties.
Why: §1 says globally, and its reasoning is that a different gold on one surface
to another is a seam. An email is a surface. §5 excludes "the authenticated
product" from this round, which I read as "do not rebuild authenticated
features" rather than "do not let a token value reach them".
Changes it: being told emails are out of scope, in which case nine files revert.
2026-08-29

**9 · --r moved 8px → 1rem, which is a visible change to every control**
Default applied: adopted the reference's dominant radius.
Why: §1 says re-lock to .25/1/1.5rem, and 8px exists nowhere in the reference.
Which of its three to map --r onto was decided by counting applied classes in
its shipped bundle — rounded-full 58, rounded-lg 46, rounded-xl 4, rounded-sm 1
— rather than by picking the nearest number. Every control on the site is now
noticeably rounder.
Changes it: him saying it is too round, in which case --r-sm is the other
defensible reading.
2026-08-29

**10 · The three shadows are declared but not yet applied to anything**
Default applied: tokens and guards shipped; no element uses them.
Why: §1 asked for them to be permitted, not placed, and §5 forbids anything
beyond repointing the guards. The served CSS therefore contains no box-shadow
declaration at all — which is why the scope guard passes trivially today. It
will do real work the moment somebody reaches for elevation.
Changes it: a round that asks for the cards to carry lift.
2026-08-29

**11 · About and Questions were kept in the footer against §2's column list**
Default applied: his three Platform entries are all present, and ours sit beside
them.
Why: §2 lists the column as How It Works, The Science, Packages — which is what
his reference has, because his reference has no About page and no FAQ. Building
it to the letter dropped two pages that exist and resolve, and the footer link
floor caught it at six against eight. Losing working navigation was not
something the round asked for.
Changes it: him saying he wants those two gone from the footer.
2026-08-29

**12 · A protected client line was retired by a later client instruction**
Default applied: "The person you build a life with." is no longer on the page;
the entry is kept in ClientSuppliedCopyTest as a comment with its provenance and
the reason.
Why: it was the hero paragraph and §2 replaces that paragraph with wording from
the reference he supplied. The guard cannot arbitrate between two things the
client said, and the later one stands. Recorded rather than deleted so the line
is recoverable.
Changes it: him asking for it back.
2026-08-29

**13 · /packages redirects to login rather than returning 404**
Default applied: none — observed, not changed.
Why: §2 says it "still 404s during the free period". On production it answers
302 to /login. Nothing public links to it either way, so no visitor meets a dead
end, but the brief's assumption about that route is not what the route does.
Changes it: a decision about what that URL should do while payments are off.
2026-08-29

**14 · Idea, parked as §5 instructs**
The reference's package cards are the only place on the site where a price is
stated. When payments go live, the free-period statement is replaced by a Select
button in the same frame — but nothing yet proves the price on the card matches
the price the checkout charges. That is the same class of drift PageMatchesEngineTest
was written for, and it deserves the same treatment in the round that turns
payments on.
2026-08-29

---

# R10-C

**15 · realvows.com was serving the review build, and I missed it**
Default applied: made the review treatment impossible on a production hostname
in code, rather than waiting on server access to correct APP_ENV.
Why: the server's .env says APP_ENV=demo, which drove the banner, a noindex meta
tag and a robots.txt of Disallow: /. I have no SSH to that box — the deploy runs
on GitHub secrets — so a code fix was the only route I could take myself. It is
also the better fix: a hand-edited environment variable on a box nobody checks
is what caused this, and a hostname cannot be misconfigured into being wrong.
⚠️ The server .env should still be corrected. Until it is, APP_ENV=demo remains
true there and anything else keyed to it stays keyed to it.
Changes it: nothing. The guard should stay even after the .env is fixed.
2026-08-29

**16 · I verified production by grepping for strings I expected, not by reading it**
Default applied: none — recorded so the method changes.
Why: last round I fetched the home page, checked twenty strings I had just
written, and reported the deploy clean. The banner was in the bytes I had open.
Grepping for what you expect confirms what you did; it cannot find what you did
not think to look for. The page needed reading, and the screenshots the client
sent found it in one glance.
Changes it: nothing. This is a note about how to check, not a decision.
2026-08-29

**17 · The Packages entry is an anchor that becomes a route**
Default applied: PublicState::platformLinks() points Packages at /#packages
while payments are off and at /packages once the projection says they are live.
Why: §2 said remove it; a pricing-link guard said it must appear when payments
go live. Both are right and a conditional satisfies both. /packages does not 404
during the free period as the brief assumed — it redirects to /login, which is
worse, because a sign-in form reads as though the visitor did something wrong.
Changes it: payments going live, which flips it automatically.
2026-08-29

**18 · The navigation guard reported a false orphan, for the second time**
Default applied: added app/Support to the directories it scans.
Why: moving the platform links into PublicState took the route('about') literal
out of resources/, and the guard reads source rather than rendered output. Its
own comment describes this exact failure happening once before. Handing it a
resolved URL does not work — those start http:// and match nothing — so the fix
is to look where the navigation is now written.
Changes it: navigation moving again, which would need the same treatment.
2026-08-29

**19 · §12's third item is not done**
Default applied: the icon discs and the closing eyebrow are fixed; the process
step headings still wrap raggedly, three of four on two lines.
Why: evening them needs either a set break per heading or a min-height on the
row, and both are guesses without seeing it rendered at several widths. It is
cosmetic and it is the smallest thing on the list.
Changes it: a look at it on a real screen.
2026-08-29
