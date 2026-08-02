{{--
    ── "See what an introduction looks like" ────────────────────────────────

    The product's signature moment, performed once on the marketing page.

    ⚠️ THIS IS THE ONLY PLACE A STRANGER *DOES* SOMETHING BEFORE SIGNING UP.

    Everything else on this page is read. This is tapped — and the thing it
    teaches is the whole mechanic in one gesture: an introduction arrives
    sealed, and it opens because you chose to open it. Explaining that in
    prose takes a paragraph nobody reads.

    ⚠️ IT REUSES THE PRODUCT'S PRIMITIVES, IT DOES NOT RESTAGE THEM.

    The veil, the sheen, the breakable seal, the compatibility ring and the
    unwipe are the same classes the dashboard uses, driven by the same
    `.rv-unsealing` switch. If the ceremony is retuned in the product it must
    change here too, without anybody remembering this file exists. A
    second implementation would drift within one release and the marketing
    page would start promising a moment the product no longer performs.

    ⚠️ SAFIYA DOES NOT EXIST, DELIBERATELY.

    She is not in DemoSeeder and she is not a member. Using a real seeded
    member here would put a made-up compatibility score against a real
    profile, and using a real member's photograph on a public marketing page
    is a different consent question entirely. The photograph is licensed
    stock, and the person in it is not claimed to be a member.

    ⚠️ NOTHING HERE MAY EXPLAIN HOW COMPATIBILITY IS CALCULATED. The rationale
    says what they have in common, never what it weighed to get there.
--}}
@php
    $score = 91;
    $photo = 'images/site/demo-match.jpg';
    $photoWebp = 'images/site/demo-match.webp';
    // 2πr for the ring's r=62, which is what the arc's dash length is built
    // from. Kept here so the markup can render fully drawn with JS disabled.
    $circumference = 389.56;
@endphp

<section class="rv-paper rv-demo" aria-labelledby="rv-demo-title">
    <div class="rv-shell rv-demo__inner" data-reveal>
        <h2 class="rv-d2 rv-demo__title" id="rv-demo-title">This is how an introduction arrives.</h2>

        {{--
            ⚠️ THE PHOTOGRAPH'S URLS ARE DATA, NOT AN <img>.

            A sealed card renders no image element at all — the same rule the
            dashboard follows, where the server never issues a URL for a
            locked match precisely so there is nothing in the DOM to recover.
            Here the file is licensed stock rather than a member's photograph,
            so the stake is lower, but a sealed introduction with the picture
            already sitting in the markup teaches the wrong thing about how
            the product works.

            JS builds the element on open.
        --}}
        <article class="rv-match rv-demo__card" data-demo-card
                 data-demo-score="{{ $score }}"
                 data-demo-photo="{{ asset($photo) }}"
                 data-demo-photo-webp="{{ asset($photoWebp) }}">
            {{--
                ⚠️ THE HIT AREA IS THE WHOLE CARD, not the seal.

                The seal is 64px and is the thing that *looks* tappable, which
                is exactly the trap — a 64px target on a phone is under the
                44px minimum once you account for how far a thumb actually
                lands from where the eye aimed. The whole card is the button,
                so the target is 420×315 and the seal stays decorative.

                Rendered as a real <button> rather than a click handler on the
                article, so it is reachable by keyboard and announces itself.
            --}}
            <button type="button" class="rv-demo__hit" data-demo-open>
                <span class="rv-visually-hidden">Open this introduction from Safiya</span>
            </button>

            <div class="rv-demo__stage">
                <div class="rv-veil rv-demo__veil">
                    <div class="rv-veil__inner">
                        <span class="rv-veil__mark" aria-hidden="true">RealVows</span>
                        <span class="rv-veil__sheen" aria-hidden="true"></span>
                    </div>

                    {{-- The same breakable seal the dashboard puts on a locked
                         card: two halves that part along a hairline. --}}
                    <span class="rv-seal rv-seal--flap" aria-hidden="true">
                        <span class="rv-seal__half rv-seal__half--l"></span>
                        <span class="rv-seal__half rv-seal__half--r"></span>
                        RV
                    </span>
                </div>
            </div>

            <div class="rv-match__head">
                <div class="rv-ring rv-ring--sm" role="img"
                     aria-label="Compatibility: {{ $score }} out of 100">
                    <svg class="rv-ring__svg" viewBox="0 0 132 132" aria-hidden="true">
                        <circle class="rv-ring__track" cx="66" cy="66" r="62" />
                        {{-- Rendered fully drawn. JS winds it back to zero and
                             lets it draw, so the card is never wrong for
                             anyone whose JS did not load. --}}
                        <circle class="rv-ring__arc" cx="66" cy="66" r="62"
                                stroke-dasharray="{{ $circumference }} {{ $circumference }}"
                                stroke-dashoffset="{{ round($circumference * (1 - $score / 100), 2) }}"
                                data-demo-arc />
                    </svg>
                    <span class="rv-ring__value" aria-hidden="true" data-demo-score-value>{{ $score }}</span>
                </div>

                <div class="rv-match__who">
                    <h3 class="rv-match__name">Safiya, 29</h3>
                    <p class="rv-match__city">Kingston, Jamaica</p>
                </div>
            </div>

            {{-- ⚠️ What they share, never how it was weighed. --}}
            <p class="rv-match__rationale">
                You both put faith at the centre, you both want children, and you are both
                looking to marry within the year.
            </p>
        </article>

        <p class="rv-demo__hint" data-demo-hint>Tap to open it.</p>

        {{-- Arrives after the ceremony, and the reset arrives after that —
             the sentence is the point of the section, the way back is only
             an accommodation. Hidden rather than absent so the reset has
             something to hide again. --}}
        <div class="rv-demo__after" data-demo-after hidden>
            <p class="rv-demo__line">
                On RealVows, this only happens when someone chooses to open it.
                Nobody browses anyone.
            </p>

            <button type="button" class="rv-demo__again" data-demo-reset hidden>
                Show me again
            </button>
        </div>
    </div>
</section>
