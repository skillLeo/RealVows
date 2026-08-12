# Device walk-list

**What I could not test, and exactly what to look for.**

I can measure viewports, assert rendered box sizes, and confirm code paths. I
cannot tell you whether pull-to-refresh *feels* right in your hand, whether a
haptic lands, or whether the collapsing bar stutters on your Samsung. Those are
the questions below. Nothing here is simulated and nothing is reported as
passing.

Do this on a real phone, on 4G rather than wifi if you can.

---

## What I already measured, so you can skip it

| Check | Method | Result |
|---|---|---|
| Horizontal scroll at 375 / 834 / 1440 | iframe harness, real viewport | Clean on all public pages |
| Touch targets under 44px | Rendered box at 375px | Clean except two inline links in sentences (documented exemption) |
| Banner weight | Markup + CSS measured | 7.4kb against a 25kb limit |
| Haptic call sites | Source scan with a control | Exactly one module, three moments |
| Reduced motion | CSS inspection | Full stop, not a slowdown |

---

## 1. Pull to refresh

**Where:** dashboard, pull down from the top.

- Does the brass ring start drawing as soon as you move, or after a lag?
- Does it feel like it resists slightly before it triggers, or does it fire too
  easily when you were only scrolling?
- **After it fires, does the page jump?** Scroll position should be preserved.
- Try it mid-scroll rather than at the very top. It should do nothing.

**Note:** there is deliberately no haptic here any more. It fired on every pull
and that is one of the three call sites I removed.

## 2. Haptics — exactly three, and no others

The only three moments that should buzz:

1. **Unseal** — opening an introduction on the dashboard
2. **Trusted contact approval received** (requires the guardian flow switched on)
3. **Mutual interest** — the other person says yes

**Walk the app for two minutes and count.** If you feel a buzz anywhere else,
tell me where. A buzz on navigation was live until this session.

Also: turn on *Reduce Motion* in iOS/Android accessibility settings. **All three
should go silent.** A vibration is motion.

## 3. Safe-area insets

**On a phone with a notch or a home indicator:**

- Rotate to landscape. Does anything fixed sit under the notch?
- Is the bottom bar clear of the home indicator, or does your thumb hit the
  system gesture area first?
- Open a sheet. Does its bottom edge clear the indicator?

## 4. The collapsing top bar

- Scroll down slowly. Does it collapse smoothly or in a jump?
- Scroll back up. Does it come back immediately or only at the very top?
- **Does it flicker** when you change direction quickly? That is the failure
  worth reporting.

## 5. Back button contract

- Open an introduction, press the system back button. Do you land where you
  were in the list, at the right scroll position?
- Open a sheet, press back. **Does it close the sheet, or leave the page
  entirely?** It should close the sheet.
- Do it three times in a row quickly.

## 6. The hero on a mid-range phone over 4G

- **How long before you see the arches?** They should paint essentially
  immediately; it is SVG, not an image.
- Do the two brass lines meet under the keystone, then rest? The cycle is
  roughly 20 seconds, so watch for a full minute.
- Is the movement smooth or does it stutter? Stutter here matters more than
  anywhere else, because it is the first thing anybody sees.
- At 375px you should see **three arches and one keystone**, not six.

## 7. PWA install

- Add to home screen. Does the icon look right, or is it a screenshot of the
  page?
- Open from the icon. Does it open without browser chrome?
- Turn on airplane mode and open it. Do you get the offline page or a browser
  error?

## 8. Forms on a real keyboard

- Tap the email field on registration. **Does the keyboard cover the field?**
- Does the number pad appear for the phone field rather than the full keyboard?
- Fill the whole registration form using only the keyboard's next button.

---

## How to report back

For anything that feels wrong, tell me: **which screen, what you did, what
happened, and what you expected.** "The bar flickers when I scroll up fast on
the dashboard" is enough to fix. "Mobile feels janky" is not.
