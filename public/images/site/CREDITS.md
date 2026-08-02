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

| File | Used on | Source | Photographer | Licence |
|---|---|---|---|---|
| `hero-a.jpg` | Home hero | ⚠️ **not recorded** | ⚠️ **not recorded** | ⚠️ **unverified** |
| `hero-b.jpg` | Home hero | ⚠️ **not recorded** | ⚠️ **not recorded** | ⚠️ **unverified** |
| `login.jpg` | Sign in | ⚠️ **not recorded** | ⚠️ **not recorded** | ⚠️ **unverified** |
| `signup.jpg` | Register | ⚠️ **not recorded** | ⚠️ **not recorded** | ⚠️ **unverified** |
| `verify.jpg` | Verification | ⚠️ **not recorded** | ⚠️ **not recorded** | ⚠️ **unverified** |
| `packages.jpg` | Packages | ⚠️ **not recorded** | ⚠️ **not recorded** | ⚠️ **unverified** |

**Action required before launch:** confirm the source and licence of all six,
or replace them. They are almost certainly Unsplash and therefore fine, but
"almost certainly" is not a licence record.

---

## The two couples — Home section 4

**Exactly two.** `couple-muslim.jpg` and `couple-christian.jpg`.

⚠️ **Not a wider spread, and specifically not Hindu or Sikh imagery.** The
client named his two audiences: Muslim and Christian. A rail of many faces
reads as a stock library and as trying to represent everyone, which this
product is not doing.

⚠️ **`hero-b.jpg` is currently wrong and must be replaced.** It shows a couple
in Hindu/Sikh wedding dress — a red turban with a kalgi and a sherwani. It is
standing in for the Muslim couple and should not ship.

### What the two need to be

- Portrait 3:4, minimum 1200px on the short edge
- **Faces in the upper third**, so a `object-position: center 22%` crop keeps
  them whole on a narrow phone
- Warm, candid, celebration-dressed, natural light, real affection — hands,
  laughter, a shared glance
- **Never posed studio work.** Never anything that reads as a stock library
- Never AI-generated people. The product's promise is verified humans

| File | Needs to show | Source | Photographer | Licence |
|---|---|---|---|---|
| `couple-muslim.jpg` | A visibly Muslim couple — nikah or walima dress | | | |
| `couple-christian.jpg` | A Christian or Western wedding couple | | | |

Drop both into `public/images/site/` with those exact names. The page picks
them up automatically; no code change is needed.

---

## Also used in the hero

`hero-a.jpg` and `hero-b.jpg` are the two photographs in the home hero. The
same rule applies: one Muslim couple, one Christian couple, faces in the upper
third. `hero-b.jpg` currently fails that and needs replacing.
