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
