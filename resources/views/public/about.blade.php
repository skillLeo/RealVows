@extends('layouts.public')

@section('title', 'About')
@section('description', 'Why RealVows exists, who it is for, and the standards everyone here is held to.')

@section('content')
    {{--
        ⚠️ THIS PAGE MAY NOT NAME A COMPANY, AND MAY NOT IMPLY ONE.

        The contracting entity is still being confirmed with the client's bank.
        The legal pages handle that correctly — they print nothing until the
        setting is filled in — and this page has to hold the same line.

        That is stricter than it sounds. It rules out a founding year, a place
        of incorporation, a team size, a "we are a company that", and anything
        else that would have to be corrected the day the entity is confirmed.
        A correction to an About page is not a copy edit; it is a public
        statement about who you are turning out to have been wrong.

        So everything here is about the product's intent and its standards,
        which are true regardless of what name eventually appears on a receipt.
        BillingIdentityTest covers the legal pages; this comment covers this
        one, because "no claims that could later conflict" is not something a
        test can check.
    --}}
    <section class="rv-shell rv-section">
        <div class="rv-stack" style="gap: 16px" data-reveal>
            <span class="rv-label rv-label--brass">RealVows</span>
            {{-- ⚠️ "Built for people who are serious" opened on a past
                 participle with no subject, which is a shape generated text
                 reaches for constantly. A full clause with a subject reads as
                 though a person wrote it, because a person would have.

                 The lead also carried a dash and a broken comparison: "the
                 person they build a life with, not the next conversation" set
                 a person against a conversation, which does not hold. --}}
            <h1 class="rv-d1" style="max-width: 22ch">We built this for people who are serious.</h1>
            <p class="rv-lead" style="max-width: 54ch">
                RealVows is for people looking to find their true soul mate. It is built for the
                person you spend your life with.
            </p>
        </div>
    </section>

    <section class="rv-panel rv-on-dark">
        <div class="rv-shell rv-section rv-stack" style="gap: 24px; max-width: 62ch" data-reveal>
            <h2 class="rv-d2">Why it works this way</h2>

            <p class="rv-body">
                Most platforms are built to keep you on them. The longer you scroll, the better
                they do. That is a reasonable way to build a game. It is a poor way to help
                somebody find the person they build a life with.
            </p>
            <p class="rv-body">
                RealVows is built the other way round. You are introduced to a small number of
                people who genuinely fit, a few at a time, and we would rather you stopped needing
                us. A platform for finding your soul mate should be trying to lose you.
            </p>

            @include('public.partials.ornament', ['tone' => 'dark'])
        </div>
    </section>

    <section class="rv-shell rv-section rv-paper">
        <div class="rv-stack rv-section__head" data-reveal>
            <p class="rv-eyebrow">What we hold everyone to</p>
            <h2 class="rv-d2">Our standards</h2>
            <span class="rv-underline" style="max-width: 180px"></span>
        </div>

        <div class="rv-grid rv-grid--3" data-stagger>
            @foreach ([
                ['Real people only', 'Every member is reviewed by a real person before they can be matched. Nobody enters the matching pool unverified.'],
                ['Privacy by default', 'Your photographs are held privately and your last name stays yours until you and someone else have both said yes.'],
                ['No games', 'Nobody is ranked against strangers and nothing is scored in public. You are introduced, or you are not.'],
            ] as [$title, $body])
                <div class="rv-card rv-stack" style="gap: 16px" data-reveal>
                    <h3 class="rv-d3">{{ $title }}</h3>
                    <span class="rv-rule rv-rule--soft"></span>
                    <p class="rv-body">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="rv-shell rv-section">
        <div class="rv-stack" style="gap: 24px; max-width: 62ch" data-reveal>
            <h2 class="rv-d2">Who it is for</h2>
            <p class="rv-body">
                Anyone 18 or over who is seeking to find their true soul mate. If you would rather
                be introduced to three people who fit than shown three hundred who do not, you are
                in the right place.
            </p>
            <p class="rv-body">
                If you are here to pass the time, this will be a disappointing place to spend it.
                That is the intended result.
            </p>

            <div>
                <a href="{{ route('register') }}" class="rv-btn rv-btn--primary">Create your free account</a>
            </div>
        </div>
    </section>
@endsection
