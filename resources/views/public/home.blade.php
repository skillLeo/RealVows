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

         ⚠️ IT IS AN INVITATION NOW, NOT A LANDING PAGE WITH A PICTURE BESIDE IT.

         This was a fifty-fifty split: words on the left, art on the right,
         divided by a hairline. That is the layout of every SaaS home page ever
         built, and no wedding invitation has ever been set that way. An
         invitation is CENTRED and symmetrical — ornament, a quiet italic line,
         the words that matter, a rule, the detail, ornament — and the whole
         thing sits inside a frame. Reading down the middle is what makes a page
         feel addressed to you rather than sold to you.

         The art is no longer a panel. It is behind everything, full bleed, and
         the words sit inside its arch. One composition instead of two columns
         competing for the same attention. --}}
    {{-- ⚠️ NOT rv-on-dark ANY MORE. The hero is cream.

         Every band of it was oxblood, and a deep maroon field reads closer to
         sombre than to celebration. Christian wedding language is ivory, white
         and gold; Muslim wedding language is rich colour WITH gold and light.
         Ivory ground, brass line and oxblood type is the register both share,
         and it is the one an invitation is actually printed in.

         The oxblood has not been thrown away — it is the ink and the button
         now, and the page still closes on a full-bleed oxblood panel. It has
         gone from being the room to being the writing in it. --}}
    <section class="rv-hero" data-hero>
        {{-- Behind the words, full bleed. Declared first so it paints first;
             it is positioned absolutely and takes part in no layout. --}}
        @include('public.partials.the-meeting')

        {{-- ⚠️ THE COPY IS INSIDE THE FRAME, NOT UNDERNEATH IT.

             The arch used to be an absolutely-positioned overlay sized in
             viewport units, which meant it agreed with the words at exactly one
             window size and broke on any zoom. The crown is now a block in
             normal flow above the text and the legs are borders on the box
             around it, so the frame is built out of the content and cannot come
             apart from it. --}}
        <div class="rv-hero__copy">
          <div class="rv-hero__frame">
            @include('public.partials.the-arch')

            <div class="rv-hero__frame-body">
              <div class="rv-hero__copy-inner rv-stack" style="gap: 20px">
                {{-- ⚠️ NO ORNAMENT HERE. The keystone at the crown of the arch,
                     directly above this line, is already the mark that opens the
                     invitation — a second lozenge 30px under it is the same
                     gesture made twice, and it cost 54px of height on a screen
                     that measured 90px short. --}}
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
                    <a href="{{ route('register') }}" class="rv-btn rv-btn--primary">Create your free account</a>
                </div>

                {{-- ⚠️ THE THREE FACTS GO LAST AND STAY SMALL. Centred, they
                     read as the line at the foot of an invitation — where,
                     when, who it is for — rather than as feature bullets. --}}
                <p class="rv-hero__eyebrow">
                    <span>Soul mate first</span>
                    <span>18+</span>
                    <span>Verified members</span>
                </p>
              </div>
            </div>
          </div>
        </div>

        {{--
            ⚠️ THE SEVEN PHOTOGRAPHS ARE GONE, AND THE ARGUMENT FOR THEM WAS
            GOOD.

            Two tiles were visibly Muslim and two visibly Western, so both
            audiences saw at a glance that the platform was for them. That was
            a market decision from the client, not decoration.

            It is replaced anyway. A page about people who have not met yet
            should not open with the faces of strangers, and every photograph
            of a person on a marketing page is a promise about who is inside.
        --}}
    </section>

    {{-- ── See what an introduction looks like ─────────────────────────────
         The only thing on this page a visitor DOES rather than reads. It sits
         directly under the hero because interaction before commitment is the
         most reliable thing there is for turning a reader into a member. --}}
    {{--
        ⚠️ THE INTRODUCTION DEMONSTRATION IS GONE, AND IT WAS THE BEST-BUILT
        THING ON THIS PAGE.

        It rendered a working imitation of an introduction arriving: the card,
        the name, the score, the sentence explaining the match, and a tap to
        open it. It was the most persuasive section here.

        It also told every visitor exactly what an introduction looks like when
        it lands, which is the one thing the client asked twice not to disclose:
        "tell them they will receive an email and a WhatsApp, but do not give
        them an idea what it will say or what it will look like."

        A rendered mock says more than any sentence would, and it is harder to
        notice in review because it is markup rather than prose.

        The partial is left in the repository rather than deleted. If he ever
        decides the demonstration is worth the disclosure, it is one line —
        and PublicPagesDescribeNoMechanismTest will fail until somebody has
        that conversation on purpose.
    --}}

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
                ['four', 'Seen by the right people', 'Your photographs go to the person you trust, never to a stranger.'],
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

    {{-- ⚠️ THE COUPLES RAIL IS REMOVED, AND THIS IS THE ONE I WOULD ARGUE
         ABOUT.

         Twelve photographs of real couples, running as #16 The Ticker. It was
         doing genuine work: proof that people like the reader have come out of
         this the other side, which is the single hardest thing for a new
         platform to claim.

         It is gone because §G is unambiguous — "no photographs of people
         anywhere on the home page" — and because the same argument that
         removed the hero mosaic applies to it: every photograph of a person on
         a marketing page is a promise about who is inside, and stock couples
         are a promise nobody can keep.

         The partial still exists at public.partials.couples-rail and is a
         one-line change to restore. This is flagged in R3-PROGRESS for the
         client rather than left as an absence he notices later. --}}

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
                {{--
                    ⚠️ WHAT IT PROMISES, NOT HOW IT IS CALCULATED.

                    This used to walk through the inputs and say both sides were
                    weighed. All of it was true and none of it was ours to
                    publish: the client has asked three times for less, and
                    explaining the scoring is the single most natural thing to
                    write on a page about matching, which is exactly why it
                    keeps coming back.

                    The promise a member cares about survives. Nobody needs the
                    arithmetic to understand that a match is not one person's
                    wishes imposed on another.
                --}}
                <p class="rv-lead" style="max-width: 54ch">
                    Every introduction here is chosen deliberately, and it is chosen for both of
                    you. A match is never one person's wishes imposed on another.
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
                Build your profile without paying anything. Your introductions are free while
                we are getting started, and anything you receive in that time stays yours.
            </p>

            <div>
                <a href="{{ route('register') }}" class="rv-btn rv-btn--primary">Create your free account</a>
            </div>
        </div>
    </section>

    @include('public.partials.arrival')

@endsection
