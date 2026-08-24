
## Committing — standing rule, added R9-C

⚠️ **Commit at every completed unit of work, never at the end of a round.**

Ten files from R9 sat uncommitted in the working tree and the client saw an
unchanged site for three days. The work was done. It had simply never left the
machine, and from inside the repository that is indistinguishable from a change
that shipped.

- Never finish a turn with a dirty tree.
- Every report includes `git status --porcelain`, and its output must be empty.
- Every report includes `git log --oneline` for the commits that round produced.
- Commits are authored `skillLeo <hassam.dev.571@gmail.com>`. No co-author
  trailers, no generated-with lines. A hook rejects them.

⚠️ **A change that was made and a change that shipped are not the same event.**
After deploying, fetch production and confirm the new bytes are being served.
Do not report a round complete from the repository.
