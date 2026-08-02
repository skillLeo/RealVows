{{--
    ── Section 4: "Two people, properly introduced" ──────────────────────────

    The romantic centrepiece, and the section carrying the couples brief.

    ⚠️ The images drift on a CSS transform loop — not a scroll listener, not
    JavaScript. Two consequences: it runs on the compositor, and it costs
    nothing on a mid-range phone over 4G.

    The rail is duplicated once so the loop is seamless. The copy is
    aria-hidden so a screen reader hears each couple once rather than twice.

    Licensing and provenance for every file: public/images/site/CREDITS.md
--}}
@php
    /*
     * The final eight, with the existing licensed site images standing in
     * until they arrive. Whatever is actually present is used, so the section
     * is reviewable now and needs no code change when the real files land.
     */
    $fallbacks = [
        'images/site/hero-a.jpg', 'images/site/hero-b.jpg', 'images/site/login.jpg',
        'images/site/signup.jpg', 'images/site/verify.jpg', 'images/site/packages.jpg',
    ];

    $rail = collect(range(1, 8))->map(function ($n) use ($fallbacks) {
        $file = "images/site/rail-{$n}.jpg";

        return [
            'src' => is_file(public_path($file)) ? $file : $fallbacks[($n - 1) % count($fallbacks)],
            'alt' => 'A couple together on their wedding day',
        ];
    });
@endphp

<section class="rv-couples rv-paper" aria-labelledby="rv-couples-line">
    <div class="rv-couples__rail">
        <div class="rv-couples__track">
            @foreach ($rail as $img)
                <figure class="rv-couples__frame">
                    <img src="{{ asset($img['src']) }}" alt="{{ $img['alt'] }}"
                         width="900" height="1200" loading="lazy" decoding="async">
                </figure>
            @endforeach

            {{-- The seamless half. Hidden from assistive technology so the
                 same couples are not announced twice. --}}
            @foreach ($rail as $img)
                <figure class="rv-couples__frame" aria-hidden="true">
                    <img src="{{ asset($img['src']) }}" alt="" width="900" height="1200"
                         loading="lazy" decoding="async">
                </figure>
            @endforeach
        </div>
    </div>

    <p class="rv-couples__line" id="rv-couples-line">
        Every introduction we make is one we would stand behind.
    </p>
</section>
