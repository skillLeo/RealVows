{{--
    ── "The Meeting" ─────────────────────────────────────────────────────────

    ⚠️ NO PHOTOGRAPH OF ANY PERSON APPEARS ON THIS PAGE.

    The hero was seven photographs of real people. It carried a genuine market
    argument — two visibly Muslim tiles and two visibly Western ones, so both
    audiences saw at a glance that the platform was for them — and it is gone
    anyway, because a page about people who have not met yet should not open
    with strangers' faces.

    ── THE IDEA ──────────────────────────────────────────────────────────────

    The product already owns the arch: the one shape a mosque and a church
    share, and the shape of a doorway you walk through to something serious.

    ⚠️ IT IS A ROOM NOW, NOT A DRAWING OF A ROOM.

    The first version laid three rows of identical arches flat on a coloured
    rectangle and washed the top of it in brass. Flat is the word: no light
    source, no floor, no distance, and a hard horizontal seam where the veil
    ran out. The client's note was "flop and ugly" and he was right.

    This is one-point perspective. You are standing INSIDE a threshold looking
    down a lit colonnade at a doorway of light at the far end. Everything is
    scaled about a single vanishing point at (500, 470), which is what makes
    the piers converge, the floor rise and the crowns fall on their own —
    perspective is not drawn here, it is a consequence of the geometry.

    Depth comes from four cues stacked, none of them a shadow:
      · scale        — each bay is 0.74 of the one in front of it
      · atmosphere   — each bay is fainter than the one in front of it
      · light        — a warm source at the far end, falling off toward you
      · occlusion    — the wall you stand in frames the whole thing

    ── WHAT MOVES, AND WHY IT IS ALL ALREADY IN THE CATALOGUE ────────────────

    Two halves climb their own side of the threshold and meet at the keystone.
    Different durations on purpose: one arrives before the other and waits.
    Two people are not symmetrical.

    Nothing here needed a new @keyframes. The arcade arrives on The Arrival
    (#1) with --arrive-scale, which reads as the room opening out of the far
    light because each bay is scaled about the vanishing point. The far window
    breathes on The Candlelight (#14). The keystone warms on rv-keystone-warm.
    A seventeenth primitive would have been a new name for motion the
    stylesheet could already describe.

    ── CONSTRAINTS THIS FILE IS UNDER ────────────────────────────────────────

    · Pure SVG and CSS. No canvas, no WebGL, no animation library. This page
      ships no Vue and has to stay fast on a mid-range Android over 4G.
    · Transform and opacity only. Nothing here can cause a layout pass.
    · Under 25kb including the markup.
    · Full stop under prefers-reduced-motion — the composition renders in its
      final state, arch complete, keystone already warm.
    · Brass, oxblood and cream. No new tokens.
    · ⚠️ NO STAR SHAPES AND NO CONSTELLATIONS. The product removed a
      date-of-birth-derived matching component last week; a hero full of points
      of light would put the imagery back as decoration. Architecture and
      typography only.
--}}
<div class="rv-meeting" aria-hidden="true">
    <svg class="rv-meeting__svg" viewBox="0 0 1000 1000" preserveAspectRatio="xMidYMid slice" focusable="false">
        <defs>
            {{-- The threshold: the arch you are standing in. It is the frame,
                 the clip and the drawn line, so the three can never drift
                 apart. --}}
            <path id="rv-threshold" d="M200 1000 L200 430 A300 300 0 0 1 800 430 L800 1000 Z" />

            <clipPath id="rv-meeting-opening">
                <use href="#rv-threshold" />
            </clipPath>

            {{-- One bay, drawn once. Scaled about the vanishing point it
                 becomes every bay in the colonnade. --}}
            <g id="rv-bay">
                <path d="M-300 530 L-300 -40 A300 300 0 0 1 300 -40 L300 530" fill="none" />
            </g>

            <radialGradient id="rv-meeting-glow">
                <stop offset="0%"   stop-color="var(--brass-bright)" stop-opacity=".5" />
                <stop offset="34%"  stop-color="var(--brass)"        stop-opacity=".18" />
                <stop offset="100%" stop-color="var(--brass)"        stop-opacity="0" />
            </radialGradient>

            {{-- The doorway of light at the far end. Ends at zero so it has no
                 bottom edge of its own — a light with an outline is a shape. --}}
            <linearGradient id="rv-meeting-window" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%"   stop-color="var(--brass-bright)" stop-opacity=".74" />
                <stop offset="55%"  stop-color="var(--brass-bright)" stop-opacity=".34" />
                <stop offset="100%" stop-color="var(--brass-bright)" stop-opacity="0" />
            </linearGradient>

            {{-- Side walls falling away into shadow. Shadows are banned as a
                 component; this is tone, which is what is left. --}}
            <linearGradient id="rv-meeting-walls" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%"   stop-color="#000" stop-opacity=".5" />
                <stop offset="32%"  stop-color="#000" stop-opacity=".04" />
                <stop offset="68%"  stop-color="#000" stop-opacity=".04" />
                <stop offset="100%" stop-color="#000" stop-opacity=".5" />
            </linearGradient>

            {{-- Light falls off with distance from its source, so the floor at
                 your feet is the darkest part of the room, not the brightest.
                 Getting this backwards is what makes rendered light look
                 painted on. --}}
            <linearGradient id="rv-meeting-near" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%"   stop-color="#000" stop-opacity="0" />
                <stop offset="62%"  stop-color="#000" stop-opacity="0" />
                <stop offset="100%" stop-color="#000" stop-opacity=".42" />
            </linearGradient>

            {{-- ⚠️ THE LIGHT ON THE FLOOR IS TWO ELLIPSES, NOT A TRAPEZIUM.

                 It was a polygon from the far door to the near corners, which
                 is geometrically what light down an aisle does and which read
                 as a carpet: a hard straight edge down each side that no
                 gradient inside the shape can soften. Light has no edges. Two
                 soft pools and the aisle hairlines carry the same perspective
                 with nothing to catch the eye. --}}
            <radialGradient id="rv-meeting-pool">
                <stop offset="0%"   stop-color="var(--brass-bright)" stop-opacity=".16" />
                <stop offset="100%" stop-color="var(--brass-bright)" stop-opacity="0" />
            </radialGradient>

            <linearGradient id="rv-meeting-aisle" x1="0" y1="1" x2="0" y2="0">
                <stop offset="0%"   stop-color="var(--brass)" stop-opacity=".4" />
                <stop offset="100%" stop-color="var(--brass)" stop-opacity="0" />
            </linearGradient>

            <radialGradient id="rv-meeting-bloom">
                <stop offset="0%"   stop-color="var(--brass-bright)" stop-opacity="1" />
                <stop offset="100%" stop-color="var(--brass-bright)" stop-opacity="0" />
            </radialGradient>
        </defs>

        {{-- ── Through the doorway ──────────────────────────────────────── --}}
        <g clip-path="url(#rv-meeting-opening)">
            <circle cx="500" cy="500" r="260" fill="url(#rv-meeting-glow)" />

            <ellipse cx="500" cy="630" rx="130" ry="90" fill="url(#rv-meeting-pool)" />
            <ellipse cx="500" cy="780" rx="330" ry="210" fill="url(#rv-meeting-pool)" opacity=".45" />

            <path class="rv-meeting__window" d="M445 588 L445 468 A55 55 0 0 1 555 468 L555 588 Z"
                  fill="url(#rv-meeting-window)" />
            <circle class="rv-meeting__candle" cx="500" cy="500" r="170" fill="url(#rv-meeting-bloom)" />

            {{-- The colonnade. --i is the depth index: it sets how faint the
                 bay is and how late it arrives, so distance is one number. --}}
            <g class="rv-meeting__bay" style="--i: 1"><use href="#rv-bay" transform="translate(500 470) scale(.74)"  /></g>
            <g class="rv-meeting__bay" style="--i: 2"><use href="#rv-bay" transform="translate(500 470) scale(.548)" /></g>
            <g class="rv-meeting__bay" style="--i: 3"><use href="#rv-bay" transform="translate(500 470) scale(.405)" /></g>
            <g class="rv-meeting__bay" style="--i: 4"><use href="#rv-bay" transform="translate(500 470) scale(.30)"  /></g>

            {{-- ── The two ─────────────────────────────────────────────────

                 ⚠️ THESE ARE ARCHES AT BODY SCALE. THEY ARE NOT DRAWINGS OF
                 PEOPLE, AND THAT IS THE WHOLE DESIGN, NOT A COMPROMISE.

                 The client asked for a man and a woman that cannot be
                 recognised as a man or a woman — no face, nothing immodest,
                 nothing a Muslim visitor should have to look away from. The
                 obvious answer is a simplified human outline, and it is the
                 wrong one: a simplified body is still a body, and every step
                 taken to make it less recognisable also makes it uglier.

                 So they are not bodies. They are the building's own shape,
                 standing at the height of a person, in the light. A mihrab
                 arch 1/6 the height of the bay behind it. There is no head, no
                 face, no limb and no anatomy anywhere in these two paths —
                 each is four commands and a semicircle. What makes them read
                 as two people is scale, the floor they stand on and the fact
                 that there are two of them close together, leaning very
                 slightly toward one another.

                 It also happens to be the better idea. The doorway you walk
                 through and the two who walk through it are made of the same
                 shape, so the metaphor needs no explaining and the composition
                 has one vocabulary instead of two.

                 ⚠️ NOT IDENTICAL, AND NOT LABELLED. One is a little taller and
                 stands a little straighter. Which is which is not stated and
                 must not be — the difference is there because two people are
                 different, not to assign a role to either of them.

                 The lean is 1.2° and 1.5°, applied about each one's own base
                 so their feet stay on the floor. It is the whole difference
                 between two objects and two people standing together, and at
                 that size nobody will ever consciously see it. --}}
            <g class="rv-meeting__figure" style="--i: 1">
                <path d="M470 690 L470 595 A14 14 0 0 1 498 595 L498 690 Z"
                      transform="rotate(1.2 484 690)" />
            </g>
            <g class="rv-meeting__figure" style="--i: 2">
                <path d="M504 690 L504 606 A14 14 0 0 1 532 606 L532 690 Z"
                      transform="rotate(-1.5 518 690)" />
            </g>

            {{-- Floor courses at each bay's base. They compress toward the
                 vanishing point because the bays do. --}}
            <g class="rv-meeting__course">
                <path d="M278 862 L722 862" />
                <path d="M336 760 L664 760" />
                <path d="M378 685 L622 685" />
            </g>

            <g class="rv-meeting__aisle">
                <path d="M200 1000 L500 470" />
                <path d="M800 1000 L500 470" />
            </g>

            <rect x="200" y="0" width="600" height="1000" fill="url(#rv-meeting-walls)" />
            <rect x="200" y="0" width="600" height="1000" fill="url(#rv-meeting-near)" />
        </g>

        {{-- ── The wall you are standing in ─────────────────────────────────
             Even-odd against the threshold, so the opening is a hole rather
             than a lighter rectangle. This is the occlusion cue: you are
             inside something, looking out. --}}
        <path class="rv-meeting__wall" fill-rule="evenodd"
              d="M0 0 H1000 V1000 H0 Z M200 1000 L200 430 A300 300 0 0 1 800 430 L800 1000 Z" />

        <circle class="rv-meeting__bloom" cx="500" cy="130" r="180" fill="url(#rv-meeting-bloom)" />

        {{-- The faint permanent trace of the arch, so the finished shape is
             visible the whole time and the bright brass is the part being
             walked. --}}
        <use class="rv-meeting__jamb" href="#rv-threshold" />

        {{-- ── The two halves ───────────────────────────────────────────────

             ⚠️ THESE ARE HALVES OF THE THRESHOLD, NOT TWO LINES ON A FLOOR.

             Each starts at its own base, climbs its own side, and the two
             complete a single arch at the keystone. Two halves finishing one
             arch is the union stated in the shape itself, using the motif the
             product already owns — the doorway a mosque and a church share.
             Nothing has to be explained. --}}
        <path class="rv-meeting__path rv-meeting__path--left"
              d="M200 1000 L200 430 A300 300 0 0 1 500 130" />
        <path class="rv-meeting__path rv-meeting__path--right"
              d="M800 1000 L800 430 A300 300 0 0 0 500 130" />

        {{-- ⚠️ THE KEYSTONE SITS AT THE APEX, WHICH IS WHERE THE TWO HALVES
             MEET. It used to sit at the CENTRE of the arc's circle, which is
             architecturally a diamond floating in a doorway. A keystone is the
             wedge at the top, and it is the stone both halves lean on, which
             is the entire reason it is the right shape for this product. --}}
        <path class="rv-meeting__keystone" d="M474 130 L500 100 L526 130 L500 160 Z" />
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
