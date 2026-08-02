{{--
    ── Section 4: "Every introduction we make is one we would stand behind" ──

    A continuous horizontal marquee of twelve photographs, like a headline
    ticker. The line underneath is the point of the section; the rail is the
    evidence for it.

    ⚠️ THE TRACK IS RENDERED TWICE, AND THE SECOND COPY IS aria-hidden.

    That is what makes the loop seamless: the animation translates the track
    by exactly -50%, which lands the start of the second copy precisely where
    the first began, so there is no jump and no gap. The duplicate is decoration
    for the eye only — a screen reader must not be read twelve photographs and
    then the same twelve again.

    ⚠️ The whole rail is one labelled image and its tiles are silent, for the
    same reason as the hero: twelve descriptive alts is noise, twelve empty
    ones with no wrapper is an accessibility failure.
--}}
<section class="rv-couples rv-paper" aria-labelledby="rv-couples-line">
    @php($rail = \App\Support\Rail::all())

    <div class="rv-marquee" role="img"
         aria-label="Couples who found each other through RealVows">
        {{-- ⚠️ Two identical copies. Changing this to one breaks the loop. --}}
        <div class="rv-marquee__track">
            @foreach ([false, true] as $isDuplicate)
                <div class="rv-marquee__set" @if ($isDuplicate) aria-hidden="true" @endif>
                    @foreach ($rail as $image)
                        <figure class="rv-marquee__tile">
                            <picture>
                                @if ($image['webp'])
                                    <source type="image/webp" srcset="{{ asset($image['webp']) }}">
                                @endif
                                <img src="{{ asset($image['src']) }}" alt=""
                                     width="560" height="747"
                                     loading="lazy" decoding="async" fetchpriority="low">
                            </picture>
                        </figure>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <p class="rv-couples__line" id="rv-couples-line">
        Every introduction we make is one we would stand behind.
    </p>
</section>
