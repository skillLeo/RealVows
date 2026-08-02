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

## Legacy images — TO BE REPLACED, NOT INVESTIGATED

Four of these have no recoverable source. **The decision is to replace them
with model-released stock before launch rather than chase provenance on files
that were never recorded.** Do not spend more time trying to identify them.

| File | Used on | Source | Status |
|---|---|---|---|
| `hero-a.jpg` | no longer displayed | [unsplash.com/photos/1591604466107-ec97de577aff](https://unsplash.com/photos/1591604466107-ec97de577aff) | ID recovered from git history; photographer still unread |
| `hero-b.jpg` | **not displayed — Hindu dress, must not ship** | [unsplash.com/photos/1599462616558-2b75fd26a283](https://unsplash.com/photos/1599462616558-2b75fd26a283) | Replace |
| `login.jpg` | Sign in | no source in any commit, no EXIF | Replace before launch |
| `signup.jpg` | Register | no source in any commit, no EXIF | Replace before launch |
| `verify.jpg` | Verification | no source in any commit, no EXIF | Replace before launch |
| `packages.jpg` | Packages | no source in any commit, no EXIF | Replace before launch |

⚠️ **Model releases.** A CC or free-stock licence covers the PHOTOGRAPH. It
does not cover the right to use an identifiable person's likeness to advertise
a service. On a marketing page the risk is low but it is not zero, and it stops
being theoretical the day real members are paying. Budget for model-released
stock (Adobe Stock or similar) for all six before launch.

---

## The two couples — SOURCED BY THE CLIENT

Both photographs are chosen by hand, from Pexels, in a browser. That is
deliberate: every automated source is closed to this environment (Openverse
rate-limits anonymously, Unsplash blocks bots, Wikimedia Commons is a heritage
archive holding paintings rather than contemporary lifestyle photography) —
and more importantly, "does this face feel right" is a judgement only a person
looking at it can make.

**Pexels License: commercial use permitted, no attribution required.** Cleaner
for a commercial client project than CC BY-SA, whose share-alike terms are
awkward on a paid site.

⚠️ **Attribution not required is not the same as attribution unrecorded.**
Record it either way.

### Live right now — Wikimedia Commons, attribution REQUIRED

These are stand-ins until the hand-picked pair lands. Both licences oblige
attribution, so this table is not optional while they are deployed.

| Slot | File | Photographer | Licence | Source |
|---|---|---|---|---|
| Muslim | `couple-muslim.jpg` | Faiaz Ahmad Emon | **CC BY 2.0** | [Sojib and Tanha](https://commons.wikimedia.org/wiki/File:Sojib_and_Tanha.jpg) |
| Christian | `couple-christian.jpg` | OJjnr | **CC BY-SA 4.0** | [Church Wedding Exclusives 67](https://commons.wikimedia.org/wiki/File:Church_Wedding_Exclusives_67.jpg) |

⚠️ Both are **wedding photographs**, which the current brief rejects. They are
displayed only because something has to be, and they will be replaced.

### Incoming — Pexels, hand-picked

Overwrite this table when the files land. Photographer and source URL go in
even though the Pexels License does not demand them.

| Slot | File | Photographer | Licence | Source |
|---|---|---|---|---|
| Muslim | `couple-muslim.jpg` | *pending* | Pexels License | *pending* |
| Christian | `couple-christian.jpg` | *pending* | Pexels License | *pending* |

### The specification they were chosen against

- Portrait, croppable to 3:4 without cutting a face or a hand
- 1200px+ on the short edge, faces in the upper third of the final crop
- **Everyday or smart-casual clothes — NOT wedding wear.** A wedding photograph
  shows two people who already found each other, which is the wrong message on
  a page trying to convert somebody who has not
- **Contemporary hijab as the only religious signal.** No bridal jewellery, no
  forehead ornament, no heavy henna styling — those read as a South Asian
  wedding first and as Muslim second, which inverts the point
- **US market.** Couples an American viewer recognises from their own city
- Mixed-heritage pairings welcome and preferred for at least one slot
- Quiet, tender, reserved. No kissing, no open-mouth laughing, no lifted poses,
  nothing physically intimate, eyes down or on each other rather than at camera

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
