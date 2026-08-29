# Reference tokens — extracted from the live stylesheets

**Source:** `https://realvows.luxoraarts.com/`
**Extracted:** 2026-08-29, R10 §2
**Method:** `curl` of the served HTML, then of every asset it links. Values below are
read out of the shipped CSS. **Nothing here is estimated or matched by eye.**

## How it was fetched, and the one caveat

The site is reachable and returns `200`. It is a Vite-built React SPA:

| Asset | Bytes | Note |
|---|---|---|
| `/` | 1,325 | shell only — `<div id="root"></div>`, all copy is in the JS |
| `/assets/index-pHKJmDnX.css` | 68,342 | Tailwind v4 build, the token source |
| `/assets/index-38aZbw3S.js` | 539,432 | React bundle, the copy source |
| Google Fonts CSS | 4,213 | two variable families |

⚠️ **The host's TLS certificate does not cover this hostname.** A plain `curl`
fails with `SSL: no alternative certificate subject name matches target host
name`. The certificate presented is the shared host's default, not one issued
for `realvows.luxoraarts.com`. The fetch was completed with certificate
verification disabled, which is acceptable for reading a public stylesheet the
client has asked us to copy, and is recorded here rather than left silent.

This is worth telling Umar: **his reference site is served on a mismatched
certificate**, so a visitor on a modern browser gets a full-page security
interstitial before they see anything. If that domain is ever shown to anyone,
it needs a certificate for its own name.

## Typefaces — this is the font complaint, and it is real

Both families are variable, loaded from the Google Fonts CDN.

| Role | Reference | What we ship today |
|---|---|---|
| Display | **`"Playfair Display", ui-serif, Georgia, serif`** — variable, `wght 400..900` | **Fraunces** |
| Sans | **`"Inter", ui-sans-serif, system-ui, sans-serif`** — variable, `wght 100..900` | Inter ✅ already correct |
| Mono | `ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace` | n/a |

**The display face is the whole difference.** Fraunces is a soft, wonky,
high-contrast face with an optical-size axis; Playfair Display is a crisp
transitional Didone. Set at the same size they do not look similar, and no
amount of size or spacing adjustment closes the gap. This is what the client
means when he says our fonts look different to his.

Weights actually requested: `100 900` (Inter), `400 900` (Playfair Display) —
i.e. the full axis in both cases, not a fixed set of instances.

## Colour

A Material 3 scheme. Primary is a deep plum-burgundy; the accent is gold.

### Core
| Token | Value |
|---|---|
| `--color-primary` | `#480027` |
| `--color-primary-container` | `#6b0b3e` |
| `--color-primary-tint` | `#c06a90` |
| `--color-on-primary` | `#fff` |
| `--color-on-primary-container` | `#ef78a9` |
| `--color-primary-fixed` | `#ffd9e4` |
| `--color-primary-fixed-dim` | `#ffb0cc` |
| `--color-on-primary-fixed` | `#3e0021` |
| `--color-on-primary-fixed-variant` | `#81204f` |
| `--color-inverse-primary` | `#ffb0cc` |
| `--color-surface-tint` | `#9f3967` |
| **`--color-gold`** | **`#c5a059`** |

`<meta name="theme-color">` is `#480027`, matching `--color-primary`.

### Secondary
| Token | Value |
|---|---|
| `--color-secondary` | `#775a19` |
| `--color-secondary-container` | `#fed488` |
| `--color-secondary-fixed` | `#ffdea5` |
| `--color-secondary-fixed-dim` | `#e9c176` |
| `--color-on-secondary` | `#fff` |
| `--color-on-secondary-container` | `#785a1a` |
| `--color-on-secondary-fixed-variant` | `#5d4201` |

### Surface and outline
| Token | Value |
|---|---|
| `--color-surface` | `#fcf9f8` |
| `--color-surface-low` | `#f6f3f2` |
| `--color-surface-lowest` | `#fff` |
| `--color-surface-container` | `#f0eded` |
| `--color-on-surface` | `#1c1b1b` |
| `--color-on-surface-variant` | `#544248` |
| `--color-inverse-surface` | `#313030` |
| `--color-inverse-on-surface` | `#f3f0ef` |
| `--color-outline` | `#877178` |
| `--color-outline-variant` | `#dac0c7` |

### Status
| Token | Value |
|---|---|
| `--color-error` | `#ba1a1a` |
| `--color-error-container` | `#ffdad6` |
| `--color-on-error-container` | `#93000a` |
| `--color-success` | `#2e6b4f` |
| `--color-success-container` | `#d9ece1` |

### Element colours — **do not port**
`--color-element-fire #b0492c`, `--color-element-earth #5d6b3a`,
`--color-element-air #4c5a8a`, `--color-element-water #1c5f66`.

These are the four classical elements of the zodiac wheel. R10 §5 deletes that
section entirely, so these four tokens are extracted for completeness and
**must not enter our token set**.

## Type scale

Tailwind v4 defaults, unmodified. Sizes in `rem`; line heights as authored.

| Step | Size | Line height |
|---|---|---|
| `xs` | `.75rem` | `calc(1 / .75)` |
| `sm` | `.875rem` | `calc(1.25 / .875)` |
| `base` | `1rem` | `1.5` |
| `lg` | `1.125rem` | `calc(1.75 / 1.125)` |
| `xl` | `1.25rem` | `calc(1.75 / 1.25)` |
| `2xl` | `1.5rem` | `calc(2 / 1.5)` |
| `4xl` | `2.25rem` | `calc(2.5 / 2.25)` |
| `5xl` | `3rem` | `1` |

`3xl` is absent from the build — it is never used on the page.

**Letter spacing:** `--tracking-tight -.025em`, `--tracking-wide .025em`,
`--tracking-wider .05em`.
**Leading:** `--leading-tight 1.25`.
**Weights referenced:** `--font-weight-medium 500`, `--font-weight-semibold 600`,
`--font-weight-bold 700`.

## Layout

| Token | Value |
|---|---|
| **`--container-page`** | **`1200px`** |
| `--container-xs … 5xl` | `20rem, 24rem, 28rem, 32rem, 36rem, 42rem, 48rem, 56rem, 64rem` |
| `--spacing` | `.25rem` (the 4px base step) |
| `--aspect-video` | `16 / 9` |

## Radius

| Token | Value |
|---|---|
| `--radius-sm` | `.25rem` |
| `--radius-lg` | `1rem` |
| `--radius-xl` | `1.5rem` |

Literals also present: `0`, `1px`, `2px`, `50%`, and `3.40282e38px` (Tailwind's
`rounded-full`, i.e. FLT_MAX).

## Shadows — **the reference has them, we ban them**

Three real values ship on that page:

```
0 1px 3px 0 #0000001a, 0 1px 2px -1px #0000001a
0 4px 20px  #1a1a1a0a
0 10px 34px #1a1a1a12
```

Our design system forbids `box-shadow` outright and has a guard enforcing it.
R10 §3 says: *"if the reference has no shadows, keep the guard as it is. If it
does, permit exactly those and no others."* It does, so the guard is repointed to
permit exactly these three and nothing else.

All three are low-alpha and near-neutral — `#1a1a1a` at 4–7% opacity. They are
elevation hints, not drop shadows.

## Motion

| Token | Value |
|---|---|
| `--default-transition-duration` | `.15s` |
| `--default-transition-timing-function` | `cubic-bezier(.4, 0, .2, 1)` |
| `--ease-out` | `cubic-bezier(0, 0, .2, 1)` |
| `--animate-spin` | `spin 1s linear infinite` |
| `--animate-pulse` | `pulse 2s cubic-bezier(.4, 0, .6, 1) infinite` |
| `--blur-sm` / `--blur-md` | `8px` / `12px` |

## One thing found in the bundle that must not be copied

The JS names **Stripe** three times. Our payment-provider guard forbids naming a
provider on any public page and R10 §8 keeps that guard enforced, so the
reference's wording is not followed here. R10 §5 already specifies the
replacement footnote: *"Secure payment. Matches are delivered privately by email
and WhatsApp."*

The bundle also contains 34 zodiac references, all inside the section R10 §5
deletes in full.
