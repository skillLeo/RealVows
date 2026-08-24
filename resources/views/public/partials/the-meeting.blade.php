{{--
    ── The hero ground ───────────────────────────────────────────────────────

    ⚠️ THE LATTICE AND THE GRAIN ARE GONE, AND THE CLIENT WAS RIGHT ABOUT WHY.

    This used to be a diagonal diaper screen at .42 opacity with a turbulence
    grain multiplied over it. Both were carefully argued and both were wrong
    against the reference he supplied: a cross-hatch behind a headline reads as
    texture competing with type, and the grain gave the whole band a slightly
    dirty cast. His words were that ours looked heavy beside a page that looks
    professional, and he is describing exactly this.

    ⚠️ WHAT THE REFERENCE ACTUALLY DOES is quieter than what we were doing. A
    pale warm wash, one soft bloom of colour behind the words, and a ROW of very
    faint arch outlines running off the top of the band. No fill, no texture, no
    noise. The arches are architecture rather than decoration: they are the same
    shape as the keystone arch that frames the headline, repeated and drawn at a
    weight you notice only after a second.

    ⚠️ HAIRLINE-SOFT, NOT HAIRLINE. At full hairline weight a row of arches
    stops being a ground and starts being a diagram.

    Still one SVG, still no image, still nothing fetched.
--}}
<div class="rv-meeting" aria-hidden="true">
    <svg class="rv-meeting__svg" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" focusable="false">
        <defs>
            {{-- The bloom behind the words. One soft radial, brass at a whisper,
                 which is what gives the pale ground its warmth without becoming
                 a colour in its own right. --}}
            <radialGradient id="rv-meeting-bloom" cx=".5" cy=".42" r=".55">
                <stop offset="0%" stop-color="var(--brass)" stop-opacity=".10" />
                <stop offset="55%" stop-color="var(--brass)" stop-opacity=".04" />
                <stop offset="100%" stop-color="var(--brass)" stop-opacity="0" />
            </radialGradient>
        </defs>

        <rect width="1440" height="900" fill="url(#rv-meeting-bloom)" />

        {{-- ⚠️ FIVE ARCHES, DRAWN OFF THE TOP. Each is a half-round on a tall
             stem, so the curve reads and the legs run out of the band rather
             than terminating inside it, which is what makes them architecture
             instead of five shapes sitting on a shelf. --}}
        <g class="rv-meeting__arches">
            @foreach ([-40, 250, 540, 830, 1120] as $x)
                <path d="M{{ $x }} 900 L{{ $x }} 320 A180 180 0 0 1 {{ $x + 360 }} 320 L{{ $x + 360 }} 900" />
            @endforeach
        </g>
    </svg>
</div>
