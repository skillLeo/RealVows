@extends('layouts.public')

@section('title', 'Find someone ready for marriage')
@section('description', 'RealVows is a marriage-focused matchmaking platform for people 18 and over. Free to join, every profile verified by a real person, matches delivered to your email and WhatsApp.')

@section('content')

    {{-- ── Hero ─────────────────────────────────────────────────────────── --}}
    <section class="rv-hero rv-on-dark">
        <div class="rv-hero__copy">
            <div class="rv-hero__copy-inner rv-stack" style="gap: 32px">
                <h1 class="rv-d1">Find someone ready for marriage.</h1>

                <p class="rv-lead" style="max-width: 46ch">
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
            </div>
        </div>

        {{-- Two couples, deliberately. One visibly Muslim, so Muslim members
             can see at a glance that this platform is for them — a market
             decision from the client, not decoration. Never AI-generated
             people: the whole pitch is verified real humans. --}}
        <div class="rv-hero__art">
            <figure class="rv-hero__frame">
                <img
                    src="https://images.unsplash.com/photo-1591604466107-ec97de577aff?auto=format&fit=crop&w=900&q=80"
                    alt="A couple standing together at their wedding celebration"
                    width="900" height="1200" fetchpriority="high" decoding="async">
            </figure>
            <figure class="rv-hero__frame rv-hero__frame--second">
                <img
                    src="https://images.unsplash.com/photo-1599462616558-2b75fd26a283?auto=format&fit=crop&w=900&q=80"
                    alt="A bride and groom in traditional dress at their wedding"
                    width="900" height="1200" loading="lazy" decoding="async">
            </figure>
            <span class="rv-pill rv-pill--on-dark rv-hero__note">Final photography to be added</span>
        </div>
    </section>

    {{-- ── Trust strip ──────────────────────────────────────────────────── --}}
    <section class="rv-trust">
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

    {{-- ── How it works ─────────────────────────────────────────────────── --}}
    <section class="rv-shell rv-section">
        <div class="rv-stack" style="gap: 12px; margin-bottom: 48px" data-reveal>
            <h2 class="rv-d2">How it works</h2>
            <span class="rv-underline" style="max-width: 220px"></span>
        </div>

        <div class="rv-grid rv-grid--3" data-stagger>
            @foreach ([
                ['01', 'Build your profile', 'Seven guided steps, saved as you go'],
                ['02', 'Get verified', "A real person checks you're you, usually within a day"],
                ['03', 'Receive your matches', 'Chosen for you and delivered to your inbox — there is nothing to search through'],
            ] as [$number, $title, $body])
                <div class="rv-card rv-card--lift rv-stack" style="gap: 20px" data-reveal>
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
        <div class="rv-shell rv-stack" style="gap: 64px">
            <div class="rv-stack" style="gap: 24px" data-reveal>
                <h2 class="rv-d2" style="max-width: 24ch">Compatibility, measured properly.</h2>
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
        </div>
    </section>

    {{-- ── Closing call to action ───────────────────────────────────────── --}}
    <section class="rv-shell rv-section">
        <div class="rv-closing rv-stack" data-reveal>
            <h2 class="rv-d2">Sign up is 100% free</h2>
            <p class="rv-lead" style="max-width: 52ch">
                Build your whole profile without paying anything. Choose a match package only when
                you're ready to be introduced.
            </p>
            <div>
                <a href="{{ route('register') }}" class="rv-btn rv-btn--primary">Create your free account</a>
            </div>
        </div>
    </section>

@endsection
