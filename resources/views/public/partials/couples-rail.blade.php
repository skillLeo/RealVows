{{--
    ── Section 4: "Two people, properly introduced" ──────────────────────────

    ⚠️ TWO COUPLES. NOT A RAIL OF EIGHT.

    One visibly Muslim, one Christian — the client's two audiences, stated
    plainly. This is deliberately NOT a wider spread: a drifting rail of eight
    faces reads as a stock library, and this product is not trying to look
    like it represents everyone. Two is a statement. Eight is decoration.

    A drifting loop also stops making sense at two images — it either repeats
    visibly or leaves gaps — so this is a still, deliberate pair. Which suits
    the register better anyway: a wedding invitation does not scroll.

    Licensing and provenance: public/images/site/CREDITS.md
--}}
@php
    /*
     * The final two, with existing images standing in until they arrive.
     * Whatever is present is used, so the section is reviewable now and needs
     * no code change when the real files land.
     */
    $couples = collect([
        [
            'file' => 'images/site/couple-muslim.jpg',
            'fallback' => 'images/site/hero-b.jpg',
            'alt' => 'A couple together on their wedding day',
        ],
        [
            'file' => 'images/site/couple-christian.jpg',
            'fallback' => 'images/site/hero-a.jpg',
            'alt' => 'A couple together on their wedding day',
        ],
    ])->map(function (array $couple) {
        $couple['src'] = is_file(public_path($couple['file'])) ? $couple['file'] : $couple['fallback'];

        return $couple;
    });
@endphp

<section class="rv-couples rv-paper" aria-labelledby="rv-couples-line">
    <div class="rv-couples__pair" data-reveal>
        @foreach ($couples as $couple)
            <figure class="rv-couples__frame">
                <img src="{{ asset($couple['src']) }}" alt="{{ $couple['alt'] }}"
                     width="900" height="1200" loading="lazy" decoding="async">
            </figure>
        @endforeach
    </div>

    <p class="rv-couples__line" id="rv-couples-line">
        Every introduction we make is one we would stand behind.
    </p>
</section>
