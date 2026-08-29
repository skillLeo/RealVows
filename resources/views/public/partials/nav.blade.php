{{--
    ── The public navigation ─────────────────────────────────────────────────

    From the client, after walking the live site: "this site should have a
    sidebar at the top instead of someone has to scroll all the way down an
    entire page. There should be some shortcuts — how it works, about us, those
    sort of things — where someone can click on three lines and see that."

    ⚠️ BLADE AND VANILLA JS. NO VUE HERE, EVER.

    These pages deliberately ship no Vue and no Inertia — see
    layouts/public.blade.php. The home page has a JS budget because members
    open it on mid-range phones over 4G in Jamaica. Pulling a framework in to
    render a hamburger would undo that for about thirty lines of behaviour.

    ⚠️ THE PRICING LINK IS CONDITIONAL AND READS A FILE, NOT THE DATABASE.

    During the free period `/packages` is a 404 by design, and a navigation
    link to a 404 is worse than no link. The answer comes from
    App\Support\PublicState, which is a generated file — see that class for why
    it is not a settings read and not a cache entry. If the projection is
    missing the link is hidden, which is the safe direction.
--}}
@php
    /*
     * ⚠️ ONE LIST, SHARED WITH THE FOOTER — R10-C §6.
     *
     * The header and the footer showed different sets on production and neither
     * matched the brief. They are now the same array, and
     * HeaderAndFooterAgreeTest fails if they ever diverge again.
     */
    $links = \App\Support\PublicState::platformLinks();

    /* Still needed by the mobile sheet's free-period note below. */
    $pricingVisible = \App\Support\PublicState::pricingLinkVisible();
@endphp

<header class="rv-panel rv-pubhead__bar">
    <div class="rv-shell rv-pubhead">
        <a href="{{ route('home') }}" class="rv-logo" aria-label="RealVows home">
            <span class="rv-logo__mark" aria-hidden="true">RV</span>
            <span class="rv-logo__word">RealVows</span>
        </a>

        {{-- Desktop: laid out inline, where there is room for it. --}}
        <nav class="rv-pubhead__nav rv-pubhead__nav--wide" aria-label="Main">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" class="rv-pubhead__link">{{ $link['label'] }}</a>
            @endforeach

            <a href="{{ route('login') }}" class="rv-pubhead__link">Sign in</a>
            <a href="{{ route('register') }}" class="rv-btn rv-btn--primary">Join free</a>
        </nav>

        {{-- Mobile: the three lines he asked for. `aria-expanded` is
             maintained by public.js; it starts closed. --}}
        <button
            type="button"
            class="rv-navtoggle"
            data-nav-toggle
            aria-expanded="false"
            aria-controls="rv-navsheet"
        >
            <span class="rv-navtoggle__lines" aria-hidden="true">
                <span></span><span></span><span></span>
            </span>
            <span class="rv-sr-only">Menu</span>
        </button>
    </div>
</header>

{{--
    ⚠️ A NATIVE <dialog>, so the browser gives us the modal semantics rather
    than us reimplementing them badly: focus is trapped by showModal(), Escape
    closes it, and the rest of the page is inert to a screen reader.

    Closed in markup. A member with no JavaScript sees the header links, which
    wrap onto a second line on a narrow screen — degraded, not broken.
--}}
<dialog id="rv-navsheet" class="rv-navsheet" data-nav-sheet aria-label="Menu">
    <div class="rv-navsheet__inner">
        <div class="rv-navsheet__head">
            <span class="rv-logo__word">RealVows</span>

            <button type="button" class="rv-navsheet__close" data-nav-close>
                <span aria-hidden="true">&times;</span>
                <span class="rv-sr-only">Close menu</span>
            </button>
        </div>

        <nav class="rv-navsheet__nav" aria-label="Main">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}">{{ $link['label'] }}</a>
            @endforeach

            @unless ($pricingVisible)
                {{-- ⚠️ NOT A LINK. During the free period there is no pricing
                     page to point at, and silence where a price used to be
                     reads as broken while a sentence reads as a gift.

                     The founding offer is shown here too when places remain,
                     because this panel is where he asked for "how to make
                     payment" to live and it is the honest answer today. --}}
                <p class="rv-navsheet__note">
                    @if (\App\Support\PublicState::foundingOfferOpen())
                        Joining is free, and the first {{ \App\Support\FoundingOffer::PLACES }}
                        members keep every introduction they receive at no cost.
                    @else
                        Joining and being introduced are free right now.
                    @endif
                </p>
            @endunless

            <a href="{{ route('login') }}">Sign in</a>
        </nav>

        <a href="{{ route('register') }}" class="rv-btn rv-btn--on-dark rv-navsheet__cta">Join free</a>
    </div>
</dialog>
