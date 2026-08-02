{{--
    ── Section 4: "Two people, properly introduced" ──────────────────────────

    ⚠️ EXACTLY TWO PHOTOGRAPHS — the same pair the hero shows. One Muslim
    couple, one Christian couple. A content decision from the client, not a
    placeholder count.

    ⚠️ NO DRIFT, NO RAIL ANIMATION. A loop of two images either repeats
    visibly or leaves gaps, and a wedding invitation does not scroll.

    The pair, their focal points and the reason each was chosen live in
    App\Support\Couples and public/images/site/CREDITS.md.
--}}
<section class="rv-couples rv-paper" aria-labelledby="rv-couples-line">
    <div class="rv-couples__pair" data-reveal>
        @foreach (\App\Support\Couples::all() as $couple)
            <figure class="rv-couples__frame">
                @if ($couple['src'])
                    <img src="{{ asset($couple['src']) }}"
                         alt="{{ $couple['alt'] }}"
                         style="object-position: {{ $couple['focal'] }}"
                         width="900" height="1200" loading="lazy" decoding="async">
                @else
                    {{-- Sealed rather than wrong. See App\Support\Couples. --}}
                    <span class="rv-awaiting" role="img"
                          aria-label="Photograph to be added: {{ $couple['describes'] }}">
                        <span class="rv-seal" aria-hidden="true">RV</span>
                        <span class="rv-awaiting__note">{{ $couple['describes'] }}</span>
                    </span>
                @endif
            </figure>
        @endforeach
    </div>

    <p class="rv-couples__line" id="rv-couples-line">
        Every introduction we make is one we would stand behind.
    </p>
</section>
