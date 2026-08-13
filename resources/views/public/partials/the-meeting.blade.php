{{--
    ── "The Meeting" ─────────────────────────────────────────────────────────

    ⚠️ NO PHOTOGRAPH OF ANY PERSON APPEARS ON THIS PAGE, AND NOTHING HERE IS A
    DRAWING OF A PERSON EITHER.

    ── THE IDEA: THE SCREEN ──────────────────────────────────────────────────

    A mashrabiya is the carved lattice screen in Islamic architecture — the
    window you can see out of but not into. It is the architecture of parda.

    That is the whole concept, and it is the product:

      · It is what modesty LOOKS like as a building, so the Muslim half of the
        audience recognises it instantly and the Western half reads it as
        ornament and craft. One shape, both audiences, no compromise between
        them — the same trick the arch was already pulling.
      · It is entirely non-figurative. Octagons and squares. There is no living
        thing depicted anywhere in this file, so the halal question never has
        to be asked, let alone answered.
      · It is the product's actual mechanic. Nobody here is on display. You are
        seen through a screen, by people who have been checked, and an
        introduction is the screen opening.

    So: a warm light behind, a brass lattice in front, and the lattice THINS
    where the words are, as though the screen has been opened just there. The
    words sit in the opening. Two planes and a light between them is where the
    depth comes from — no shadow is used or needed.

    ⚠️ NO STARS, AND THE RULE WAS CHECKED RATHER THAN ASSUMED.

    The obvious Islamic pattern is a girih rosette, and a girih rosette is an
    eight-pointed star. The design system bans star shapes outright — the
    product removed date-of-birth matching and will not have that idea creep
    back as decoration. An octagon-and-square tessellation is as authentic, as
    common in real screenwork, and contains no star at any scale. The ban is
    kept to the letter, not argued with.

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

                 ⚠️ EVERY EDGE IS DRAWN EXACTLY ONCE. THAT IS WHY THIS IS SIX
                 SEGMENTS AND NOT ONE OCTAGON.

                 A closed octagon in a tile shares its flat edges with the next
                 tile along, so every vertical and horizontal in the screen
                 gets painted twice — double alpha, and the lattice reads as a
                 plaid of alternating line weights. Drawing the left flat and
                 the top flat only, plus all four diagonals, tiles into a
                 perfectly even screen: the right and bottom flats arrive from
                 the neighbouring tiles.

                 29.29 and 70.71 are 100 × (2 − √2)/2 and its complement — the
                 vertices of a regular octagon 100 wide. --}}
            <pattern id="rv-mashrabiya" width="100" height="100" patternUnits="userSpaceOnUse">
                <path d="M0 70.71 L0 29.29
                         M29.29 0 L70.71 0
                         M0 29.29 L29.29 0
                         M70.71 0 L100 29.29
                         M100 70.71 L70.71 100
                         M29.29 100 L0 70.71" />
            </pattern>

            {{-- The screen is opened where the words are. White shows the
                 lattice, black hides it; the hole is generous and very soft,
                 because a hard edge would read as a spotlight. --}}
            <radialGradient id="rv-meeting-open" cx=".5" cy=".47" r=".54">
                <stop offset="0%"   stop-color="#000" />
                <stop offset="46%"  stop-color="#000" />
                <stop offset="78%"  stop-color="#888" />
                <stop offset="100%" stop-color="#fff" />
            </radialGradient>
            <mask id="rv-meeting-screen">
                <rect width="1200" height="1000" fill="url(#rv-meeting-open)" />
            </mask>

            {{-- The light behind the screen. --}}
            <radialGradient id="rv-meeting-glow" cx=".5" cy=".47" r=".5">
                <stop offset="0%"   stop-color="var(--brass-bright)" stop-opacity=".26" />
                <stop offset="38%"  stop-color="var(--brass)"        stop-opacity=".11" />
                <stop offset="100%" stop-color="var(--brass)"        stop-opacity="0" />
            </radialGradient>

            <radialGradient id="rv-meeting-bloom">
                <stop offset="0%"   stop-color="var(--brass-bright)" stop-opacity="1" />
                <stop offset="100%" stop-color="var(--brass-bright)" stop-opacity="0" />
            </radialGradient>

            {{-- The arch fades out toward its feet rather than stopping at the
                 bottom of the frame. A line that ends is a line; a line that
                 fades is a building continuing past the edge. --}}
            <linearGradient id="rv-meeting-jamb" x1="0" y1="1" x2="0" y2="0">
                <stop offset="0%"   stop-color="var(--brass)" stop-opacity="0" />
                <stop offset="34%"  stop-color="var(--brass)" stop-opacity=".38" />
                <stop offset="100%" stop-color="var(--brass)" stop-opacity=".38" />
            </linearGradient>
        </defs>

        {{-- Light first, then the screen in front of it. --}}
        <rect width="1200" height="1000" fill="url(#rv-meeting-glow)" />
        <circle class="rv-meeting__candle" cx="600" cy="470" r="420" fill="url(#rv-meeting-bloom)" />

        <g mask="url(#rv-meeting-screen)">
            <rect class="rv-meeting__screen" width="1200" height="1000" fill="url(#rv-mashrabiya)" />
        </g>

        {{-- ── The arch the invitation is set inside ─────────────────────── --}}
        <path class="rv-meeting__jamb" d="M300 1000 L300 480 A300 300 0 0 1 900 480 L900 1000" />

        {{-- ⚠️ THESE ARE HALVES OF ONE ARCH, NOT TWO LINES ON A FLOOR.
             Each starts at its own base, climbs its own side, and the two
             complete a single arch at the keystone. Two halves finishing one
             arch is the union stated in the shape itself, using the motif the
             product already owns — the doorway a mosque and a church share.
             Nothing has to be explained. --}}
        <path class="rv-meeting__path rv-meeting__path--left"
              d="M300 1000 L300 480 A300 300 0 0 1 600 180" />
        <path class="rv-meeting__path rv-meeting__path--right"
              d="M900 1000 L900 480 A300 300 0 0 0 600 180" />

        <circle class="rv-meeting__bloom" cx="600" cy="180" r="200" fill="url(#rv-meeting-bloom)" />

        {{-- ⚠️ THE KEYSTONE SITS AT THE APEX, WHICH IS WHERE THE TWO HALVES
             MEET. It is the wedge at the top, and it is the stone both halves
             lean on, which is the entire reason it is the right shape for this
             product. --}}
        <path class="rv-meeting__keystone" d="M572 180 L600 146 L628 180 L600 214 Z" />
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
