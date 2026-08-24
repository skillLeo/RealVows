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
    faint arch outlines. No fill, no texture, no noise.

    ⚠️ THE ARCHES FADE AT BOTH ENDS, AND THAT IS THE WHOLE POINT OF THEM.

    They used to run from y=900 straight to the edge of the band, so on any
    window tall enough to show the foot of the hero they stopped dead in a row —
    a hard horizontal line of nine severed legs across the page. The client's
    word was "cutting off" and that is exactly what it was: a cropped picture,
    not architecture.

    A mask now takes them to nothing before they reach either edge, so they
    arrive out of the light at the top and dissolve into it at the foot. Nothing
    terminates anywhere a reader can see.

    ⚠️ AND THE VIEWBOX IS 1920 WIDE, NOT 1440, BECAUSE `slice` SCALES.

    At 1440 the whole row was blown up by 1.78 on a 2560px window and four
    enormous arches filled the screen. Drawn wider, with more of them, the same
    slice keeps each arch between roughly 200 and 320 CSS px whatever the window
    is doing.

    ⚠️ HAIRLINE-SOFT, NOT HAIRLINE. At full hairline weight a row of arches
    stops being a ground and starts being a diagram.

    Still one SVG, still no image, still nothing fetched.
--}}
<div class="rv-meeting" aria-hidden="true">
    <svg class="rv-meeting__svg" viewBox="0 0 1920 1000" preserveAspectRatio="xMidYMin slice" focusable="false">
        <defs>
            {{-- The bloom behind the words. One soft radial, brass at a whisper,
                 which is what gives the pale ground its warmth without becoming
                 a colour in its own right. --}}
            <radialGradient id="rv-meeting-bloom" cx=".5" cy=".40" r=".58">
                <stop offset="0%" stop-color="var(--brass)" stop-opacity=".10" />
                <stop offset="55%" stop-color="var(--brass)" stop-opacity=".04" />
                <stop offset="100%" stop-color="var(--brass)" stop-opacity="0" />
            </radialGradient>

            {{-- ⚠️ WHITE HERE IS LUMINANCE, NOT A COLOUR. A mask reads
                 brightness, so #fff means "keep" and transparent means "drop".
                 There is no palette decision on this element and no token that
                 would mean anything if one were used. --}}
            <linearGradient id="rv-meeting-fade" x1="0" y1="0" x2="0" y2="1">
                {{-- ⚠️ GONE BY 74%, NOT BY 100%, BECAUSE `slice` CROPS.

                     The fade is measured in viewBox units and the viewBox is
                     cropped, not fitted, so on a wide window the band shows
                     only the top ~78% of it. A ramp that reached zero at 100%
                     was therefore chopped at around half opacity — fainter
                     than the hard row of severed legs it replaced, and still
                     the same horizontal line. Ending it well inside the
                     viewBox means the legs have already gone whatever the
                     crop turns out to be. --}}
                <stop offset="0%" stop-color="#fff" stop-opacity="0" />
                <stop offset="14%" stop-color="#fff" stop-opacity="1" />
                <stop offset="44%" stop-color="#fff" stop-opacity="1" />
                <stop offset="74%" stop-color="#fff" stop-opacity="0" />
            </linearGradient>

            <mask id="rv-meeting-mask">
                <rect width="1920" height="1000" fill="url(#rv-meeting-fade)" />
            </mask>
        </defs>

        <rect width="1920" height="1000" fill="url(#rv-meeting-bloom)" />

        {{-- ⚠️ NINE ARCHES, SPRINGING FROM THE SAME LINE. Each is a half-round
             on a tall stem. Drawn from -120 so the row is never centred on the
             headline: an arch directly behind the words would read as a frame
             around them, which is the thing that came out of this hero. --}}
        <g class="rv-meeting__arches" mask="url(#rv-meeting-mask)">
            @foreach (range(-120, 1800, 240) as $x)
                <path d="M{{ $x }} 1000 L{{ $x }} 430 A120 120 0 0 1 {{ $x + 240 }} 430 L{{ $x + 240 }} 1000" />
            @endforeach
        </g>
    </svg>
</div>
