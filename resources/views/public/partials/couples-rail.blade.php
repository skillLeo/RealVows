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
                <img src="{{ asset($couple['src']) }}"
                     alt="{{ $couple['alt'] }}"
                     style="object-position: {{ $couple['focal'] }}"
                     width="900" height="1200" loading="lazy" decoding="async">
            </figure>
        @endforeach
    </div>

    <p class="rv-couples__line" id="rv-couples-line">
        Every introduction we make is one we would stand behind.
    </p>
</section>
