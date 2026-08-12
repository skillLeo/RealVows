@extends('layouts.public')

{{--
    ⚠️ SOUL MATE LEADS. MARRIAGE IS THE DESTINATION, NOT THE DOOR.

    From the client, after walking the live site: "the word you want to use is
    not marriage, because marriage frightens a lot of people. It is more about
    finding your soul mate — that resonates especially with women and also with
    men."

    He has not banned the word. His own sentence ends with it. He has DEMOTED
    it: marriage is where this is going, mentioned last and softly; soul mate is
    the frame and the first thing anyone reads. MarriageLeadTest holds that line
    over rendered output and keeps the allow-list of legitimate uses current.
--}}
@section('title', 'Find your true soul mate')
@section('description', 'RealVows is for people 18 and over seeking to find their true soul mate. Free to join, every member verified, introductions brought to your email and WhatsApp.')

@section('content')

    {{-- ── Hero ─────────────────────────────────────────────────────────────
         The photography is given more room than the words, and the words are
         set the way an invitation sets them: a quiet italic line, then the
         names, then the detail. --}}
    <section class="rv-hero rv-on-dark" data-hero>
        <div class="rv-hero__copy">
            <div class="rv-hero__copy-inner rv-stack" style="gap: 28px">
                <p class="rv-eyebrow rv-hero__eyebrow-line rv-arrive-step">For those who mean it</p>

                <h1 class="rv-d1" data-arrival-words>Find your true soul mate.</h1>

                <span class="rv-hero__flourish rv-arrive-step"></span>

                {{-- The client's own line, corrected only for person so that it
                     addresses the reader. He sent "The person to build a life
                     with." --}}
                <p class="rv-lead" style="max-width: 44ch">
                    The person you build a life with. Joining is free, and everyone here has been
                    checked.
                </p>

                {{--
                    ⚠️ THE FOUNDING OFFER IS COUNTED, NOT TYPED.

                    The client asked for "be one of the first 100 people to
                    create your account and get matched for free". Written into
                    this template as a sentence, it is true until the hundred
                    and first person joins and then it is a lie on the home
                    page, with nothing in the product aware of it.

                    App\Support\FoundingOffer counts verified members and the
                    scheduler projects the remainder into PublicState. When the
                    places run out this band disappears and the general free
                    period wording below carries on. An unreadable projection
                    reads as CLOSED, because a promise must never be made on a
                    guess.

                    ⚠️ HIS EMPHASIS, IN THE DESIGN SYSTEM'S OWN VOICE. He wrote
                    MATCHED and FREE in capitals. Shouting is the register of a
                    discount site and this one is built on restraint, so the
                    weight lands through the brass accent instead. The emphasis
                    is his; the way it is expressed is ours.
                --}}
                @if (\App\Support\PublicState::foundingOfferOpen())
                    <p class="rv-founding">
                        <span class="rv-founding__mark">Founding members</span>
                        Be one of the first {{ \App\Support\FoundingOffer::PLACES }} people to join
                        and every introduction you receive is <strong>free, permanently</strong>.
                    </p>
                @endif

                <div>
                    <a href="{{ route('register') }}" class="rv-btn rv-btn--on-dark">Create your free account</a>
                </div>

                <p class="rv-hero__eyebrow">
                    <span>Soul mate first</span>
                    <span>18+</span>
                    <span>Verified members</span>
                </p>

                {{-- Settles the panel, which the arcade left leaning to the
                     right. Arrives last, after everything with meaning in
                     it. --}}
                <div class="rv-hero__fleuron rv-arrive-step">
                    @include('public.partials.ornament', ['tone' => 'dark', 'rules' => false])
                </div>
            </div>
        </div>

        {{--
            ⚠️ SEVEN PHOTOGRAPHS, MIXED SIGNALS, NEVER AI-GENERATED PEOPLE.

            Two tiles are visibly Muslim and two are visibly Western, so both
            audiences see at a glance that the platform is for them. That is a
            market decision from the client, not decoration, and it is the
            reason the manifest records a signal per tile rather than leaving
            it to whoever picks the files.

            The whole pitch is verified real humans, so no image here may ever
            be generated. Two of the seven are deliberately NOT people — a
            detail shot cannot be got wrong, and a grid of seven faces reads
            as a stock library where five faces and two quiet details read as
            a designed page.

            Everything else — placement, arrival, the mount — is in
            App\Support\Mosaic and the .rv-mosaic block.
        --}}
        <div class="rv-hero__art">
            @include('public.partials.hero-mosaic')
        </div>
    </section>

    {{-- ── See what an introduction looks like ─────────────────────────────
         The only thing on this page a visitor DOES rather than reads. It sits
         directly under the hero because interaction before commitment is the
         most reliable thing there is for turning a reader into a member. --}}
    @include('public.partials.introduction-demo')

    {{-- ── The promise ─────────────────────────────────────────────────────
         One line, centred, given a whole band to itself. A page that never
         pauses feels like a brochure; this is the pause. --}}
    <section class="rv-paper rv-promise">
        <div class="rv-shell rv-stack rv-promise__inner" data-reveal>
            @include('public.partials.ornament')

            <p class="rv-vow">
                Nobody here is passing the time. Everyone on RealVows is looking for their soul
                mate, and for the lasting relationship that follows.
            </p>

            @include('public.partials.ornament')
        </div>
    </section>

    {{-- ── Trust strip ─────────────────────────────────────────────────── --}}
    <section class="rv-trust rv-paper">
        <div class="rv-shell rv-trust__grid" data-stagger>
            {{-- ⚠️ Every description ends with a full stop. They used to end
                 four different ways on one row, which is the sort of thing a
                 reader feels without being able to name. --}}
            @foreach ([
                ['diamond', 'Free to join', 'Build your entire profile without paying anything.'],
                ['check', 'Verified identity', 'Nobody is introduced to you unchecked.'],
                ['four', 'Four photos', 'Enough to show who you are, few enough to finish in one sitting.'],
                ['envelope', 'Email and WhatsApp', 'Your introductions come to you, wherever you are.'],
            ] as [$icon, $title, $body])
                <div class="rv-trust__item rv-stack" style="gap: 16px" data-reveal>
                    <span class="rv-icon-box">@include('public.partials.icon', ['name' => $icon])</span>
                    <h2 class="rv-d3">{{ $title }}</h2>
                    <p class="rv-body">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ── 4 · Two people, properly introduced ─────────────────────────── --}}
    @include('public.partials.couples-rail')

    {{-- ── How it works ──────────────────────────────────────────────────
         Shared with /how-it-works. Edit the partial, never a copy of it. --}}
    @include('public.partials.how-it-works')

    {{--
        ── Compatibility ────────────────────────────────────────────────────
        This section replaces "Compatibility starts with the stars." from the
        approved HTML. The mechanism behind the score is the client's
        commercial secret and stays internal; what is described here is
        entirely true and is what members actually give us — their values,
        their pace, the life they are trying to build.
    --}}
    <section class="rv-panel rv-compat rv-on-dark">
        <div class="rv-shell rv-stack" style="gap: 56px">
            <div class="rv-stack rv-section__head" data-reveal>
                <p class="rv-eyebrow">Not a guess</p>
                <h2 class="rv-d2" style="max-width: 22ch">Compatibility, measured properly.</h2>
                <span class="rv-underline" style="max-width: 200px"></span>
                <p class="rv-lead" style="max-width: 54ch">
                    Every introduction begins with a compatibility score out of 100. It is built
                    from what you tell us: the values you rank highest, the life you are working
                    towards, and what you are looking for in somebody else. Both sides are weighed.
                    A match is never one person's wishes imposed on another.
                </p>
            </div>

            <div class="rv-card rv-card--accent rv-compat__panel" data-reveal>
                @include('public.partials.compatibility-mark', ['score' => 87])
            </div>

            <div data-reveal>
                @include('public.partials.ornament', ['tone' => 'dark'])
            </div>
        </div>
    </section>

    {{-- ── 6 · What arrives ─────────────────────────────────────────────── --}}
    @include('public.partials.what-arrives')

    {{-- ── 7 · This is not for everyone ─────────────────────────────────── --}}
    @include('public.partials.not-for-everyone')

    {{-- ── 8 · Sealed until it is opened ────────────────────────────────── --}}
    @include('public.partials.sealed')

    {{-- ── The closing invitation ───────────────────────────────────────
         ⚠️ FULL-BLEED OXBLOOD-DEEP, NOT CREAM.

         This is the last thing on the page and the only place left to ask.
         It shipped on cream, which made it the fifth pale band in a row and
         let the eye slide past it — the page simply ran out rather than
         arriving anywhere. Returning to the hero's own colour closes the
         page where it opened, and the button is the only bright thing in
         the panel.

         ⚠️ THE FLEURON GOES ABOVE THE BUTTON. Below it, the ornament is the
         last thing read and the call to action is something you scrolled
         past; above it, the ornament announces the button. --}}
    <section class="rv-panel rv-on-dark rv-invitation">
        <div class="rv-shell rv-stack rv-invitation__inner" data-reveal>
            @include('public.partials.ornament', ['tone' => 'dark', 'rules' => false])

            <p class="rv-eyebrow">No card, no catch</p>

            <h2 class="rv-d1 rv-invitation__line">Joining is free.</h2>

            {{-- ⚠️ TRUE IN BOTH STATES. This used to say "choose a match package
                 when you are ready", which is a sentence about a page that
                 returns 404 during the free period. It now describes what
                 actually happens now, and stays true when payments arrive. --}}
            <p class="rv-lead" style="max-width: 46ch">
                Build your whole profile without paying anything. Your introductions are free while
                we are getting started, and anything you receive in that time stays yours.
            </p>

            <div>
                <a href="{{ route('register') }}" class="rv-btn rv-btn--on-dark">Create your free account</a>
            </div>
        </div>
    </section>

    @include('public.partials.arrival')

@endsection
