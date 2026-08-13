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
| Frame pacing at 4× CPU throttle | Headless Chrome over CDP, `scripts/frame-benchmark.mjs` | Median 8.3ms, worst 15.0ms on scrolling pages. Budget is 16.7ms |

### The frame benchmark, in full

Run it yourself with `node scripts/frame-benchmark.mjs` against a local server.
It drives the Chrome already on the machine, so there is nothing to install.

| Page | Median | p95 | Worst | Frames over 16.7ms | Scrolled |
|---|---|---|---|---|---|
| Home (public, no framework) | 8.3ms | 9.3ms | 9.9ms | 0 of 360 | 4332px |
| Dashboard | 8.3ms | 9.2ms | 9.7ms | 0 of 360 | 4031px |
| Introductions | 8.3ms | 9.2ms | 15.0ms | 0 of 359 | 3498px |
| Admin dashboard | 8.4ms | 9.4ms | 11.6ms | 0 of 360 | 2294px |
| Admin verifications | 8.4ms | 9.6ms | 42.7ms | 1 of 360 | 0px |

**The throttle is verified, not assumed.** A fixed amount of work took 5ms
unthrottled and 20ms throttled. Ratio exactly 4.0, and the run aborts if that
ratio comes back below 1.5. The first version of that control span a loop until
the wall clock said 200ms, which takes 200ms at every throttle rate by
construction, and it reported a ratio of 1.01. I was one step from telling you
CPU throttling does not work in this Chrome. The throttle was fine. The control
was measuring its own exit condition.

**Two honest limits.**

The last row scrolled 0px. `AdminLayout` scrolls an inner container beside a
fixed sidebar rather than scrolling the window, so that page was measured
*static under load* rather than scrolling. Its 42.7ms outlier is a single frame
on a page that was not moving, which is a weaker measurement than the four above
it. The `scrolled` column is printed precisely so you can see which rows are
which instead of taking my word for it.

And headless Chrome does not lock `requestAnimationFrame` to a display refresh,
which is why the medians sit near 8ms rather than 16.7ms. So this measures the
cost of the render loop, not frames delivered to a screen. That is the right
proxy for the question — a loop costing 8.3ms at 4× throttle has room inside a
16.7ms budget — but it is a proxy, and the real phone in your hand is still the
answer to §1 through §8 below.

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
