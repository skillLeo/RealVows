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

## The two couples — INSTALLED

Both sourced from Wikimedia Commons, cropped to 3:4 with the focal point set
by eye against the real file.

| Slot | File | Photographer | Licence | Source |
|---|---|---|---|---|
| Muslim | `couple-muslim.jpg` | Faiaz Ahmad Emon | **CC BY 2.0** | [Sojib and Tanha](https://commons.wikimedia.org/wiki/File:Sojib_and_Tanha.jpg) |
| Christian | `couple-christian.jpg` | OJjnr | **CC BY-SA 4.0** | [Church Wedding Exclusives 67](https://commons.wikimedia.org/wiki/File:Church_Wedding_Exclusives_67.jpg) |

⚠️ **CC BY and CC BY-SA both require attribution.** This table is that
attribution. If the photographs ever appear somewhere that is not this
repository, the credit has to travel with them.

### Focal points, and why

| Slot | Focal | Reason |
|---|---|---|
| Muslim | `50% 38%` | Faces sit ~38% down; the umbrellas take the top third |
| Christian | `50% 20%` | Faces ~20% down, veil and bouquet below, greenery above |

### The register both must share

Quiet, tender, reserved, warm. Modesty on the home page is a **product
requirement**, not a preference: this is a marriage-first platform courting a
Muslim audience and positioning itself against casual dating.

**Rejected on that basis:** a beach photograph of a kissing couple. It was
beautiful, and beauty was not the criterion — it read as physical intimacy on
the first screen, which is the wrong signal for this product.

⚠️ **When you swap either photograph, re-set its focal point by eye** and
check the crop at 375, 834 and 1440. A new image with an old focal point
silently reintroduces a crop that cuts faces. `CouplesPhotographyTest`
enforces that a focal point exists and is sane; only a person can tell whether
it is right for a given photograph.

### If you replace one

Drop the file in as `couple-muslim.jpg` or `couple-christian.jpg` (or
`rail-1.jpg` / `rail-2.jpg` — both names work). Portrait 3:4, 1200px+ short
edge, faces in the upper third, no kissing, no camera-facing poses, a
ceremonial setting over a photoshoot. Then update this table.
