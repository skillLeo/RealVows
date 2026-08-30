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

## Resolved — the four unsourced images, 3 August 2026

⚠️ **These had no recorded source and no EXIF, and that is now closed.**

They were inherited with the project and no commit, filename or metadata
recorded where they came from. "Almost certainly Pexels" is not a licence
record — on a platform that will take payments, an image whose provenance
nobody can state is a liability sitting on the sign-up page.

They were not investigated further. Three were **replaced** with photographs
whose licence is recorded below, and the fourth was **deleted**, because it
was referenced by nothing at all.

| File | Was | Now |
|---|---|---|
| `login.jpg` | no source in any commit, no EXIF | cottonbro studio — [6284651](https://www.pexels.com/photo/6284651/) |
| `signup.jpg` | no source in any commit, no EXIF | Ron Lach — [9652793](https://www.pexels.com/photo/9652793/) |
| `packages.jpg` | no source in any commit, no EXIF | Gustavo Fring — [7155850](https://www.pexels.com/photo/7155850/) |
| `verify.jpg` | no source in any commit, no EXIF | **Deleted.** Referenced by no stylesheet, view or component |

All three replacements are Pexels License: free for commercial use, no
attribution required, recorded here anyway because attribution not required is
not the same as attribution unrecorded.

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

### ⚠️ The two must be distinguishable by faith at a glance

This is the entire reason there are two photographs, and it has now been
missed twice.

The Muslim slot carries a **contemporary hijab**. The Christian slot must
therefore carry **no head covering at all** — and that means no ornate
jewelled headpiece, no gele or wrap, and no heavily embellished gold or
beaded gown either. Those read as Eastern styling regardless of the wearer's
actual faith, so the pair comes across as two Muslim couples and the contrast
the section exists to make simply does not happen.

**What the Christian slot needs:** plain Western clothing, uncovered hair,
quiet and reserved in the same register as the left photograph. A small cross
necklace is the clearest Christian signal available without returning to
wedding imagery.

**Rejected on this basis:** a Yoruba couple in fila and gele, and a bride in
a jewelled headpiece and gold beaded gown. Both were beautiful photographs.
Neither was legible as Christian next to a hijab.

### ⚠️ The two must also match in scale

Identical frames make a mismatch in the photographs themselves obvious, and
one tight portrait beside one wide one reads as two different pages.

**The measurable version: a head should occupy roughly 20–25% of the frame
height, and you should see both people from about the waist up with clear
space above their heads.** The current Muslim photograph sits at ~22%. A
head-and-shoulders close-up lands near 55% and cannot be rescued by a focal
point — the crop can only ever remove more, never pull back. Scale is a
property of the source file, so it has to be judged before download.

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

**There is exactly one crop shape: 3:4, everywhere.** A 2:3 photograph loses
about 11% of its height to it, top and bottom, and nothing off the sides. A
focal point set against a phone is therefore automatically right on a desktop.

⚠️ That was not true until 2 Aug 2026. The hero used to release the ratio
above 880px and resolve to **0.52** — a tall narrow column that cut 15–22% off
the WIDTH instead. Two crop shapes meant two chances to slice a face, and the
desktop one was invisible unless you went looking. `CouplesPhotographyTest`
now fails if anything reintroduces it.

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

