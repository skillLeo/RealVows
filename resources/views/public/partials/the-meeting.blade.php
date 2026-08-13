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
    Keep the arch, empty it of photographs, and let it be architecture.

    Two brass lines enter from the left and right edges and travel inward along
    the colonnade at SLIGHTLY DIFFERENT SPEEDS — they are not symmetrical,
    because two people are not. They meet beneath the central keystone, which
    warms briefly. Then they rest. Roughly every twenty seconds.

    Two paths converging under a doorway. It says what the photographs were
    trying to say without a single face.

    ── CONSTRAINTS THIS FILE IS UNDER ────────────────────────────────────────

    · Pure SVG and CSS. No canvas, no WebGL, no animation library. This page
      ships no Vue and has to stay fast on a mid-range Android over 4G.
    · Transform and opacity only. Nothing here can cause a layout pass.
    · Under 25kb including the markup.
    · Full stop under prefers-reduced-motion — the composition renders in its
      final state, lines already met, keystone already warm.
    · Brass, oxblood and cream. No new tokens.
    · ⚠️ NO STAR SHAPES AND NO CONSTELLATIONS. The product removed a
      date-of-birth-derived matching component last week; a hero full of points
      of light would put the imagery back as decoration. Architecture and
      typography only.
--}}
<div class="rv-meeting" aria-hidden="true">
    <svg class="rv-meeting__svg" viewBox="0 0 1200 560" preserveAspectRatio="xMidYMid slice" focusable="false">
        <defs>
            {{-- One arch, drawn once, placed many times. Keeps the payload
                 small and every arch identical. --}}
            <path id="rv-arch" d="M0 240 L0 96 A56 56 0 0 1 112 96 L112 240" />

            <linearGradient id="rv-meeting-fade" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="var(--brass)" stop-opacity=".55" />
                <stop offset="100%" stop-color="var(--brass)" stop-opacity="0" />
            </linearGradient>

            <radialGradient id="rv-keystone-bloom">
                <stop offset="0%" stop-color="var(--brass-bright)" stop-opacity=".5" />
                <stop offset="100%" stop-color="var(--brass-bright)" stop-opacity="0" />
            </radialGradient>
        </defs>

        {{-- ── Layer 3: furthest. Smallest, faintest, drifts least. ──────── --}}
        <g class="rv-meeting__layer rv-meeting__layer--far">
            @foreach ([120, 300, 480, 660, 840, 1020] as $x)
                <use href="#rv-arch" x="{{ $x }}" y="150" transform="scale(1)"
                     class="rv-meeting__arch" />
            @endforeach
        </g>

        {{-- ── Layer 2: middle. ──────────────────────────────────────────── --}}
        <g class="rv-meeting__layer rv-meeting__layer--mid">
            @foreach ([40, 260, 480, 700, 920] as $x)
                <use href="#rv-arch" x="{{ $x }}" y="118" class="rv-meeting__arch" />
            @endforeach
        </g>

        {{-- ── Layer 1: nearest, and the central arch. ────────────────────
             The middle one is taller and carries the keystone, so the eye is
             given somewhere to arrive. --}}
        <g class="rv-meeting__layer rv-meeting__layer--near">
            <use href="#rv-arch" x="-40" y="80" class="rv-meeting__arch" />
            <use href="#rv-arch" x="1128" y="80" class="rv-meeting__arch" />

            <path class="rv-meeting__arch rv-meeting__arch--central"
                  d="M486 460 L486 150 A114 114 0 0 1 714 150 L714 460" />

            {{-- ⚠️ THE KEYSTONE SITS AT THE APEX, WHICH IS WHERE THE TWO
                 HALVES MEET.

                 It used to sit at (600,150) — the CENTRE of the arc's circle,
                 not the crown of the arch. Architecturally that is a diamond
                 floating in a doorway. A keystone is the wedge at the top, and
                 it is the stone both halves lean on, which is the entire
                 reason it is the right shape for this product. --}}
            <circle class="rv-meeting__bloom" cx="600" cy="36" r="110"
                    fill="url(#rv-keystone-bloom)" />
            <path class="rv-meeting__keystone"
                  d="M578 36 L600 12 L622 36 L600 60 Z" />
        </g>

        {{-- ── The two halves. ───────────────────────────────────────────

             ⚠️ THESE ARE HALVES OF THE CENTRAL ARCH, NOT TWO LINES ON A FLOOR.

             They were two horizontal strokes travelling inward along the
             colonnade to meet in the middle. That reads as "two paths cross",
             which is a smaller idea than the one available: each half starts
             at its own base, climbs its own side, and the two complete a
             single arch at the keystone.

             Two halves finishing one arch is the union stated in the shape
             itself, using the motif the product already owns — the doorway a
             mosque and a church share. Nothing has to be explained.

             Each traces the faint central arch beneath it, so the finished
             shape is visible the whole time and the bright brass is the part
             being walked.

             Different durations on purpose. One arrives before the other and
             waits at the top. Two people are not symmetrical. --}}
        <path class="rv-meeting__path rv-meeting__path--left"
              d="M486 460 L486 150 A114 114 0 0 1 600 36" />
        <path class="rv-meeting__path rv-meeting__path--right"
              d="M714 460 L714 150 A114 114 0 0 0 600 36" />

        {{-- A floor line, so the colonnade stands on something. --}}
        <line class="rv-meeting__floor" x1="0" y1="460" x2="1200" y2="460" />

        <rect class="rv-meeting__veil" x="0" y="0" width="1200" height="560"
              fill="url(#rv-meeting-fade)" />
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
