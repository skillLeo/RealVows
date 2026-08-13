{{--
    ── "The Meeting" ─────────────────────────────────────────────────────────

    ⚠️ NO PHOTOGRAPH OF ANY PERSON APPEARS ON THIS PAGE, AND NOTHING HERE IS A
    DRAWING OF A PERSON EITHER.

    ── THE IDEA: THE SCREEN ──────────────────────────────────────────────────

    A lattice screen with light behind it, and the lattice thins where the words
    are, as though it had been opened just there. The words sit in the opening.
    Two planes and a light between them is where the depth comes from — no
    shadow is used or needed, which matters, because shadows are banned.

    ⚠️ IT WAS A MASHRABIYA, AND THAT WAS A MISTAKE.

    The first version used an octagon-and-square tessellation because it is
    Islamic screenwork — the architecture of parda, chosen deliberately so the
    Muslim half of the audience would recognise it.

    That is exactly why it was wrong. This platform serves Muslims AND
    Christians, and an Islamic geometric lattice is not neutral: to a Christian
    visitor it says "this is a Muslim site" before a single word is read. The
    arch works for both audiences because a mosque and a church genuinely share
    it. The lattice had no such property, and picking a motif that belongs to
    one half of the market is a market decision made by accident.

    A diagonal diaper is the honest version of the same idea. Crossed diagonals
    forming a lozenge field are Gothic tracery and Victorian tile AND Islamic
    screenwork AND the guilloche on a wedding invitation. Nobody reads it as
    belonging to one faith, because it does not. It is also quieter, which the
    composition needed anyway.

    ⚠️ NO STARS. The design system bans star shapes — date-of-birth matching was
    removed so that idea could not return as decoration — and the obvious
    Islamic pattern, a girih rosette, is an eight-pointed star. A lozenge field
    contains no star at any scale. The ban is kept to the letter.

    ── THE ARCH IS STILL THE ARCH ────────────────────────────────────────────

    Two halves climb their own side and meet at the keystone. Different
    durations: one arrives before the other and waits. Two people are not
    symmetrical. It is the best idea this product has and nothing above
    replaces it — the arch is now the frame the invitation is set inside.

    ── CONSTRAINTS THIS FILE IS UNDER ────────────────────────────────────────

    · Pure SVG and CSS. No canvas, no WebGL, no animation library.
    · Transform and opacity only. Nothing here can cause a layout pass.
    · The lattice is ONE tile in a <pattern>, so the whole screen costs about
      three hundred bytes and one paint no matter how large the viewport is.
    · Full stop under prefers-reduced-motion.
    · Brass, oxblood and cream. No new tokens.
--}}
<div class="rv-meeting" aria-hidden="true">
    <svg class="rv-meeting__svg" viewBox="0 0 1200 1000" preserveAspectRatio="xMidYMid slice" focusable="false">
        <defs>
            {{-- ── The lattice ──────────────────────────────────────────────

                 ⚠️ TWO STROKES. THAT IS THE ENTIRE SCREEN.

                 The diagonals run corner to corner, so they join the diagonals
                 of the neighbouring tiles into unbroken lines across the whole
                 field — no edge is ever painted twice and there is no seam to
                 find. The octagon this replaced needed six carefully chosen
                 segments to avoid drawing its shared flats twice; a lozenge
                 field needs none of that care, which is usually the sign of the
                 right primitive.

                 ⚠️ THE PATH CARRIES ITS OWN CLASS, AND IT HAS TO.

                 Pattern content inherits down the DOM — <pattern>, <defs>,
                 <svg> — and never from the element referencing the pattern.
                 Styling the <rect> reaches nothing in here. Worse, a `fill`
                 declaration on that rect DELETES the screen, because CSS beats
                 the fill="url(...)" presentation attribute. Both mistakes
                 shipped together once and the hero rendered as an empty field
                 with no error raised anywhere. --}}
            <pattern id="rv-diaper" width="44" height="44" patternUnits="userSpaceOnUse">
                <path class="rv-meeting__lattice" d="M0 0 L44 44 M44 0 L0 44" />
            </pattern>

            {{-- The screen is opened where the words are. White shows the
                 lattice, black hides it; the hole is generous and very soft,
                 because a hard edge would read as a spotlight. --}}
            {{-- ⚠️ THE PLATEAU IS SIZED BY LEGIBILITY, NOT BY TASTE.

                 The fully-open region ran to 46% of the radius, which cleared
                 the headline and left the tracked caps at the foot sitting on
                 lattice. Text on a lattice line measured 3.92:1 against a 4.5
                 requirement — the pattern was quietly eating the small type.

                 The screen is now fully open across the whole text column and
                 only begins to close outside it. "The screen opens where the
                 words are" was always the idea; this is the size the words
                 actually are. --}}
            <radialGradient id="rv-meeting-open" cx=".5" cy=".44" r=".72">
                <stop offset="0%"   stop-color="#000" />
                <stop offset="58%"  stop-color="#000" />
                <stop offset="82%"  stop-color="#999" />
                <stop offset="100%" stop-color="#fff" />
            </radialGradient>
            <mask id="rv-meeting-screen">
                <rect width="1200" height="1000" fill="url(#rv-meeting-open)" />
            </mask>

            {{-- The light behind the screen. --}}
            <radialGradient id="rv-meeting-glow" cx=".5" cy=".36" r=".40">
                {{-- ⚠️ MEASURED, NOT EYEBALLED. At .26 the centre of the hero
                     came out (227,210,189) — a tan, not lit paper — and the
                     tracked-caps line failed AA against it at 4.16:1. These
                     numbers are set by the contrast of the text that sits on
                     top of them, which is the only thing that decides how
                     strong a background light is allowed to be. --}}
                <stop offset="0%"   stop-color="var(--brass)" stop-opacity=".09" />
                <stop offset="38%"  stop-color="var(--brass)" stop-opacity=".04" />
                <stop offset="100%" stop-color="var(--brass)"        stop-opacity="0" />
            </radialGradient>

            <radialGradient id="rv-meeting-bloom">
                <stop offset="0%"   stop-color="var(--brass)" stop-opacity="1" />
                <stop offset="100%" stop-color="var(--brass)" stop-opacity="0" />
            </radialGradient>

        </defs>

        {{-- Light first, then the screen in front of it. --}}
        <rect width="1200" height="1000" fill="url(#rv-meeting-glow)" />
        <circle class="rv-meeting__candle" cx="600" cy="370" r="360" fill="url(#rv-keystone-bloom)" />

        <g mask="url(#rv-meeting-screen)">
            <rect class="rv-meeting__screen" width="1200" height="1000" fill="url(#rv-diaper)" />
        </g>

    </svg>

    {{-- ⚠️ STATIC GRAIN, IN ITS OWN SVG SO THE FILTER IS NEVER RE-EVALUATED
         ALONGSIDE THE ANIMATED ONE.

         Shadows are banned by the design system, so depth has to come from
         tone, hairlines and a little film grain. numOctaves is 2: the cost of
         feTurbulence rises with octaves and there is no visible gain past that
         at 5% opacity.

         ⚠️ NOTHING HERE MAY EVER BE ANIMATED. Animating baseFrequency, or
         feeding this into feDisplacementMap, is a documented way to bring a
         mid-range Android to a crawl. --}}
    <svg class="rv-meeting__grain" focusable="false">
        <filter id="rv-grain">
            <feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="2" stitchTiles="stitch" />
        </filter>
        <rect width="100%" height="100%" filter="url(#rv-grain)" />
    </svg>
</div>
