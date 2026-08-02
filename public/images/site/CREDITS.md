# Photography credits

Every image shipped in `public/images/site/` must have a row here: what it
is, where it came from, who took it, and the licence it ships under.

## Why this file exists

⚠️ **It did not exist until Stage 14, and that was a real gap.** Six images
shipped in Phase 1 with no record of their provenance. Nobody could answer
"are we licensed to use this?" without going back to whoever downloaded them.

On a platform whose entire promise is *verified real people*, the photography
is not decoration — it is a claim. Two rules follow from that:

1. **Marketing photography of couples ships permanently and that is normal.**
   Licensed stock on a marketing page is not a misrepresentation; nobody
   thinks the couple on a home page is a member.
2. **Stock photography presented as a MEMBER never ships.** The demonstration
   profiles use stock and must be replaced before real members join.
3. **Never AI-generated people, anywhere.** The product's promise is verified
   humans. A generated face on the page selling that promise would be the
   single most damaging thing on the site.

## Requirements for every image

- Licensed for commercial use, with the licence named below
- A real photograph of real people — never generated
- Caption verified: the alt text must describe what is actually in the frame
- Source URL recorded so the licence can be re-checked

---

## Currently shipped

Provenance recovered from git history — the images were **hotlinked to
Unsplash** in the approved design before being downloaded and self-hosted
(commit `d4382e6`). Two of the six URLs survive in history; four do not.

| File | Used on | Source | Photographer | Licence |
|---|---|---|---|---|
| `hero-a.jpg` | Hero + couples (Christian slot) | [unsplash.com/photos/1591604466107-ec97de577aff](https://unsplash.com/photos/1591604466107-ec97de577aff) | ⚠️ **open the URL and record** | Unsplash Licence (confirm) |
| `hero-b.jpg` | Hero + couples (Muslim slot) | [unsplash.com/photos/1599462616558-2b75fd26a283](https://unsplash.com/photos/1599462616558-2b75fd26a283) | ⚠️ **open the URL and record** | Unsplash Licence (confirm) |
| `login.jpg` | Sign in | ⚠️ **no source in history** | ⚠️ **unknown** | ⚠️ **unverified** |
| `signup.jpg` | Register | ⚠️ **no source in history** | ⚠️ **unknown** | ⚠️ **unverified** |
| `verify.jpg` | Verification | ⚠️ **no source in history** | ⚠️ **unknown** | ⚠️ **unverified** |
| `packages.jpg` | Packages | ⚠️ **no source in history** | ⚠️ **unknown** | ⚠️ **unverified** |

**How the two known ones were recovered:** the original `images.unsplash.com`
URLs are still in the git history of the design markup. The photo ID is the
provenance — opening the URL above shows the photographer and the licence.
Unsplash blocks automated requests from this environment, so **the two names
have to be read by a person and written in here.** Two minutes of work.

**The four unknown ones have no recoverable source.** No EXIF (the image
pipeline strips it, correctly), no URL in any commit. They must either be
re-sourced with a recorded licence, or replaced. Do not ship them as final.

⚠️ **`hero-b.jpg` is no longer displayed anywhere, and must not be.** It shows
a couple in Hindu wedding dress — a red turban with a kalgi, and a swastika on
the bride's lehenga.

It was standing in for the Muslim couple, which was wrong: on a product built
for Muslim and Christian members that is not a neutral placeholder, it is the
wrong statement made on the first screen anybody sees.

**The Muslim slot now has no fallback at all.** It renders a sealed panel
naming what belongs there. An obviously empty frame says "this is not
finished"; a wrong photograph says something about who this product is for,
confidently and incorrectly. `CouplesPhotographyTest` fails if any page
references `hero-b.jpg` again.

---

## The two couples

**Exactly two photographs, and the same pair appears in the hero AND the
couples section.** One Muslim couple, one Christian couple.

This is a content decision from the client: no other religion, no wider
spread. Two separate sets would be two licences to maintain and two slightly
different stories on one page.

Drop the files into `public/images/site/` under **either** naming — the code
accepts both, so nobody has to remember which:

| Slot | Accepted filenames | Needs to show |
|---|---|---|
| Muslim | `couple-muslim.jpg` or `rail-1.jpg` | A visibly Muslim couple — nikah or walima dress |
| Christian | `couple-christian.jpg` or `rail-2.jpg` | A Christian or Western wedding couple |

No code change is needed; the pages pick them up and the "Final photography
to be added" pill disappears by itself.

### Specification

- Portrait **3:4**, minimum **1200px** on the short edge
- **Faces in the upper third of the frame.** Both placements crop to a fixed
  ratio, and a face low in the frame cannot be kept safe at every width
- Warm, candid, celebration-dressed, natural light, real affection
- **Never posed studio work.** Never anything that reads as a stock library
- **Never AI-generated people.** The product's promise is verified humans

### ⚠️ Focal points — re-set these whenever an image changes

`object-fit: cover` with the default `center` crops blindly. It has no idea
where the couple is in a given frame, so it will slice through a face at one
width and look fine at another. **That is the bug that cut the top of a
groom's headwear off on a phone.**

Each photograph therefore carries a hand-set focal point in
`App\Support\Couples`, as `object-position: horizontal% vertical%`.

| Slot | Focal point | Why |
|---|---|---|
| Muslim | `50% 28%` | ⚠️ Provisional — set for the placeholder, **must be re-checked** |
| Christian | `50% 25%` | ⚠️ Provisional — set for the placeholder, **must be re-checked** |

**The procedure, when you swap an image:**

1. Open the photograph and find the vertical position of the faces as a
   percentage from the top — usually 20–35% in a wedding portrait
2. Set that as the second value in `App\Support\Couples::PAIR`
3. Record it in the table above with a sentence on why
4. Check the rendered card at **375px, 834px and 1440px** and look at what
   the crop line actually touches — not "does it look fine", but "does the
   crop cross a face, a hand, or the join between the two people"

`CouplesPhotographyTest` enforces that a focal point exists, is not the
default centre, and is not below the middle of the frame. **It cannot check
whether the point is right for a particular photograph — only a person can.**
