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

### Live — Pexels, hand-picked by the client

| Slot | File | Photographer | Licence | Source |
|---|---|---|---|---|
| Muslim | `couple-muslim.jpg` | Pavel Danilyuk | Pexels License | [pexels.com/photo/8525680](https://www.pexels.com/photo/8525680/) |
| Christian | `couple-christian.jpg` | Chidy | Pexels License | [pexels.com/photo/24051124](https://www.pexels.com/photo/24051124/) |

Photographer and photo ID are read off the download filenames, which Pexels
generates as `pexels-{photographer}-{id}.jpg`. Neither name was typed from
memory.

Both were downscaled to 1500px on the long edge, quality 82, EXIF stripped, at
roughly 300KB each. The originals were 3840px and 3168px wide — around 8MB
of photograph on a page that needs about 0.6MB of it.

The Wikimedia Commons stand-ins they replaced (Faiaz Ahmad Emon, CC BY 2.0;
OJjnr, CC BY-SA 4.0) are gone from the repository, so their attribution
obligation has gone with them.

### The specification they were chosen against

- Portrait, 2:3 or 3:4, croppable without cutting a face or a hand
- 1200px+ on the short edge
- **Contemporary hijab as the only religious signal in the Muslim slot.** No
  forehead ornament, no heavy henna styling — those read as a South Asian
  wedding first and as Muslim second, which inverts the point
- **US market.** Couples an American viewer recognises from their own city
- Quiet, tender, reserved. No kissing, no open-mouth laughing, no lifted poses,
  eyes down or on each other rather than at camera

⚠️ **The no-wedding-wear rule was applied to the Muslim slot and relaxed for
the Christian slot**, by the client, choosing by eye. The Muslim photograph is
a couple at home in everyday clothes; the Christian one is a bride in a beaded
gown with her groom behind her. Recorded because the rule as originally
written would reject a file that is deliberately shipped, and a specification
that disagrees with what is on the page misleads whoever reads it next.

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
`rail-1.jpg` / `rail-2.jpg` — both names work), downscale it to 1500px on the
long edge, then set its focal point and update the table above.

⚠️ **There are TWO crop shapes, and the obvious one is not the tight one.**

| Where | Ratio | What gets cut |
|---|---|---|
| Couples section, every width | 3:4 | ~11% of the height — top and bottom |
| Hero below 880px | 3:4 | same |
| **Hero at 880px and up** | **0.52 – 0.57** | **~15–22% of the WIDTH — left and right** |

The desktop hero is a tall narrow column, not a 3:4 card. A photograph that
crops perfectly on a phone can still lose somebody's head sideways there. It
is the constraint that decides the horizontal half of the focal point, and it
is invisible unless you look for it.

To set a focal point honestly, compute the exact crop rather than eyeballing
the whole frame — for the 3:4 case, on a 1500×2250 file:

```
excess = 2250 - (1500 / 0.75) = 250px
offset = excess × Y%          # Y is the second number in the focal point
magick couple-x.jpg -crop 1500x2000+0+<offset> +repage -resize 400x out.png
```

Then look at `out.png`. Both current values were chosen this way, and the
first guess was wrong both times: 41% sliced the crown of the hijab, and 29%
clipped a hand at the bottom edge. Neither was visible in the full frame.

