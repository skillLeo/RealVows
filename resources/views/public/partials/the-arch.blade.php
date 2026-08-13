{{--
    ── The arch ──────────────────────────────────────────────────────────────

    ⚠️ THIS IS THE CROWN ONLY. THE LEGS ARE BORDERS ON THE BOX BELOW IT.

    The arch was one SVG sized in viewport units, and it broke the moment
    anybody zoomed. Browser zoom shrinks the CSS viewport but not the type, so
    `min(720px, 90vw, 66svh)` got smaller while the headline stayed the same
    size — the words grew straight out through the sides of their own frame. On
    a phone the same arithmetic cropped the jambs off the screen and left a
    curve floating over the text with no legs under it.

    The fix is not better numbers. It is to stop the frame knowing about the
    viewport:

      · This crown sits in NORMAL FLOW above the content and is width: 100% of
        it. Its height comes from aspect-ratio, so it is always exactly as tall
        as its own geometry requires and never more.
      · The two legs are left and right BORDERS on the box underneath. A border
        is exactly as tall as its box, forever, at every zoom level and every
        font size, with no arithmetic to get wrong.

    Nothing here can drift out of alignment with the words, because the frame
    is not positioned relative to the words — it is made of them.

    ⚠️ IT IS A SEGMENTAL ARCH, NOT A SEMICIRCLE, AND THAT IS A LAYOUT DECISION.

    A semicircle over a 680px opening rises 340px — a third of a phone screen
    spent on empty curve before the first word, and the measured reason the hero
    did not fit a laptop at 100% zoom. This rises 175 on 720, a true circular
    segment struck from radius 458, so it is still one honest arc of one circle,
    just a calm one. R = (W² / 4 + h²) / 2h = (129600 + 30625) / 350 = 458.
--}}
<div class="rv-meeting__cap" aria-hidden="true">
    <svg viewBox="0 0 720 210" preserveAspectRatio="xMidYMid meet" focusable="false">
        <defs>
            <radialGradient id="rv-keystone-bloom">
                <stop offset="0%"   stop-color="var(--brass)" stop-opacity="1" />
                <stop offset="100%" stop-color="var(--brass)" stop-opacity="0" />
            </radialGradient>
        </defs>

        {{-- The archivolt: a second curve struck from the same centre, 14 in.
             A real arch is a band of concentric curves, and that second line is
             most of what separates drawn architecture from a round-topped
             box. --}}
        <path class="rv-meeting__jamb rv-meeting__jamb--inner"
              d="M18 210 A444 444 0 0 1 702 210" />

        {{-- The faint permanent trace, so the finished shape is visible the
             whole time and the bright brass is the part being walked. --}}
        <path class="rv-meeting__jamb" d="M0 210 A458 458 0 0 1 720 210" />

        {{-- The imposts: the course the arch springs from. Without them the
             arch is a hoop resting on two lines. --}}
        <g class="rv-meeting__impost">
            <path d="M0 210 L46 210" />
            <path d="M674 210 L720 210" />
        </g>

        {{-- ⚠️ THESE ARE HALVES OF ONE ARCH, NOT TWO LINES ON A FLOOR.
             Each climbs its own side and the two complete a single arch at the
             keystone. Two halves finishing one arch is the union stated in the
             shape itself, using the motif the product already owns — the
             doorway a mosque and a church share. Nothing has to be explained.

             Different durations on purpose. One arrives before the other and
             waits at the top. Two people are not symmetrical. --}}
        <path class="rv-meeting__path rv-meeting__path--left"
              d="M0 210 A458 458 0 0 1 360 35" />
        <path class="rv-meeting__path rv-meeting__path--right"
              d="M720 210 A458 458 0 0 0 360 35" />

        <circle class="rv-meeting__bloom" cx="360" cy="35" r="120"
                fill="url(#rv-keystone-bloom)" />

        {{-- ⚠️ THE KEYSTONE SITS AT THE APEX, WHICH IS WHERE THE TWO HALVES
             MEET. It is the wedge at the top, and it is the stone both halves
             lean on, which is the entire reason it is the right shape for this
             product. --}}
        <path class="rv-meeting__keystone" d="M336 35 L360 9 L384 35 L360 61 Z" />
    </svg>
</div>
