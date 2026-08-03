@extends('layouts.public')

@section('title', 'Find someone ready for marriage')
@section('description', 'RealVows is a marriage-focused matchmaking platform for people 18 and over. Free to join, every profile verified by a real person, matches delivered to your email and WhatsApp.')

@section('content')

    {{-- ── Hero ─────────────────────────────────────────────────────────────
         The photography is given more room than the words, and the words are
         set the way an invitation sets them: a quiet italic line, then the
         names, then the detail. --}}
    <section class="rv-hero rv-on-dark" data-hero>
        <div class="rv-hero__copy">
            <div class="rv-hero__copy-inner rv-stack" style="gap: 28px">
                <p class="rv-eyebrow rv-hero__eyebrow-line rv-arrive-step">For those who mean it</p>

                <h1 class="rv-d1" data-arrival-words>Find someone ready for marriage.</h1>

                <span class="rv-hero__flourish rv-arrive-step"></span>

                <p class="rv-lead" style="max-width: 44ch">
                    Free to join. Every profile verified by a real person. Matches delivered to your
                    email and WhatsApp.
                </p>

                <div>
                    <a href="{{ route('register') }}" class="rv-btn rv-btn--on-dark">Create your free account</a>
                </div>

                <p class="rv-hero__eyebrow">
                    <span>Marriage-first</span>
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
                Nobody here is passing the time. Everyone on RealVows is looking for the person
                they are going to marry.
            </p>

            @include('public.partials.ornament')
        </div>
    </section>

    {{-- ── Trust strip ─────────────────────────────────────────────────── --}}
    <section class="rv-trust rv-paper">
        <div class="rv-shell rv-trust__grid" data-stagger>
            @foreach ([
                ['diamond', 'Free to join', 'Sign up and build your full profile at no cost'],
                ['check', 'Verified identity', 'Every member reviewed by a person before they can be matched'],
                ['four', 'Four photos', "Enough to be real, not so many it's a chore"],
                ['envelope', 'Email and WhatsApp', 'Your matches come to you, wherever you are'],
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

    {{-- ── How it works ────────────────────────────────────────────────── --}}
    <section class="rv-shell rv-section rv-paper">
        <div class="rv-stack rv-section__head" data-reveal>
            <p class="rv-eyebrow">Three steps, then we take over</p>
            <h2 class="rv-d2">How it works</h2>
            <span class="rv-underline" style="max-width: 220px"></span>
        </div>

        <div class="rv-grid rv-grid--3" data-stagger>
            @foreach ([
                ['01', 'Build your profile', 'Seven guided steps, saved as you go'],
                ['02', 'Get verified', "A real person checks you're you, usually within a day"],
                ['03', 'Receive your matches', 'Chosen for you and delivered to your inbox — there is nothing to search through'],
            ] as [$number, $title, $body])
                <div class="rv-card rv-card--lift rv-step rv-stack" style="gap: 20px" data-reveal>
                    <span class="rv-numeral rv-step__number">{{ $number }}</span>
                    <h3 class="rv-d3">{{ $title }}</h3>
                    <span class="rv-rule rv-rule--soft"></span>
                    <p class="rv-body">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </section>

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
                    Every introduction begins with a compatibility score out of 100, built from what
                    you tell us about yourself — the values you rank highest, the life you are
                    working towards, how you actually live, and what you are looking for in
                    somebody else. Both sides are weighed, so a match is never one person's wishes
                    imposed on another's.
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

            <h2 class="rv-d1 rv-invitation__line">Sign up is 100% free</h2>

            <p class="rv-lead" style="max-width: 46ch">
                Build your whole profile without paying anything. Choose a match package only when
                you're ready to be introduced.
            </p>

            <div>
                <a href="{{ route('register') }}" class="rv-btn rv-btn--on-dark">Create your free account</a>
            </div>
        </div>
    </section>

    @include('public.partials.arrival')

@endsection
