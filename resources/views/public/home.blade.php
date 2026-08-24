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
{{-- ⚠️ The channel named here must be one the platform can actually use. --}}
@section('description', 'RealVows is for people 18 and over seeking to find their true soul mate. Free to join, every member verified, introductions brought to your email'.($whatsappDelivery ?? false ? ' and WhatsApp' : '').'.')

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
            {{-- ⚠️ THE OLD CENTRED ARCH IS GONE, AND IT WAS THE ONE COMPETING.

                 the-arch drew a second, separate arch directly behind the
                 badge and the headline, with a keystone diamond at its crown.
                 It made sense when the ground was a lattice and needed a frame.
                 Against the new arch ground it is a duplicate: two different
                 arch systems crossing each other behind the same words, which
                 is what the client was pointing at.

                 The partial is left in the repository rather than deleted, so
                 the reasoning behind the keystone survives if anybody wants it
                 back. Nothing includes it now. --}}

            <div class="rv-hero__frame-body">
              <div class="rv-hero__copy-inner rv-stack" style="gap: 20px">
                {{-- ⚠️ NO ORNAMENT HERE. The keystone at the crown of the arch,
                     directly above this line, is already the mark that opens the
                     invitation — a second lozenge 30px under it is the same
                     gesture made twice, and it cost 54px of height on a screen
                     that measured 90px short. --}}
                {{-- ⚠️ A PILL, NOT A BARE LABEL. The reference's confidence in
                     this position comes from the hairline enclosing the words,
                     which reads as a mark rather than as a caption. Same brass
                     caps at the same tracking, so nothing about the type has
                     moved. --}}
                <p class="rv-hero__badge rv-arrive-step">
                    {{-- ⚠️ THE DOT IS WHY THE REFERENCE'S PILL READS AS A MARK.
                         An outlined pill of tracked caps is a caption with a
                         border round it. One brass dot at the head of it turns
                         the same words into a label, and it costs eight pixels. --}}
                    <span class="rv-hero__badge-dot" aria-hidden="true"></span>
                    <span>For those who mean it</span>
                </p>

                {{-- ⚠️ THE BREAK IS SET, NOT LEFT TO THE CONTAINER.
                     "Find your true / soul mate." breaks where the meaning
                     breaks. Left to wrap, 360 and 390 put "soul" alone on the
                     second line and "mate." orphaned on a third. --}}
                <h1 class="rv-d-hero" data-arrival-words>Find your true<br class="rv-hero__break">
                    soul mate.</h1>

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
                    {{--
                        ⚠️ "PERMANENTLY" WAS ATTACHED TO THE WRONG NOUN, AND THE
                        TERMS SAY IT CORRECTLY.

                        It read "be one of the first 100 people to join and every
                        introduction you receive is free, permanently", which a
                        reader takes as: the first hundred accounts are matched
                        free forever. Nothing enforces that. There is no founding
                        flag on a user, and once the free period ends the charging
                        path applies to everybody including the hundred.

                        What IS enforced is per introduction: unlockMatchFree
                        stamps free_period_granted_at, and an unlocked match can
                        never be charged again. So what you receive during the
                        free period stays open forever, which is exactly what the
                        Terms already promise.

                        ⚠️ HIS CAPITALS ARE BACK ON THESE TWO WORDS ONLY. Free
                        signup is the one message the client asked personally to
                        be unmissable and 100% FREE is how he wrote it. The rest
                        of his emphasis is still carried by the brass rather than
                        by shouting.
                    --}}
                    <p class="rv-founding">
                        <span class="rv-founding__mark">Founding members</span>
                        Be one of the first {{ \App\Support\FoundingOffer::PLACES }} people to join
                        and get matched <strong>100% FREE</strong>. Every introduction you receive
                        while we are getting started stays open permanently.
                    </p>
                @endif

                {{-- ⚠️ TWO ACTIONS SIDE BY SIDE, WHICH R9 §6.2 ASKED FOR AND
                     I BUILT AS ONE. The reference pairs a filled primary with
                     an outlined secondary, both the same height, and the second
                     is what somebody who is not ready to commit reaches for. A
                     single button makes the page a demand rather than an
                     invitation. --}}
                <div class="rv-hero__actions">
                    <a href="{{ route('register') }}" class="rv-btn rv-btn--primary">Create your free account</a>
                    <a href="{{ route('how-it-works') }}" class="rv-btn rv-btn--outline">See how it works</a>
                </div>

                {{-- One line, small, under the actions. --}}
                <p class="rv-hero__micro">Free to join. Free to be checked. Nothing to pay to begin.</p>

                {{-- ⚠️ THE THREE FACTS GO LAST AND STAY SMALL. Centred, they
                     read as the line at the foot of an invitation — where,
                     when, who it is for — rather than as feature bullets. --}}
                {{-- ⚠️ THE TRUST RAIL. A single row of short qualifiers
                     divided by vertical hairlines, above a rule, exactly as the
                     reference sets it. Four items, and every one describes an
                     OUTCOME: none of them names a method, a photograph count or
                     any mechanism.

                     It collapses to two rows of two on a phone rather than
                     becoming a strip that scrolls sideways. --}}
                {{-- ⚠️ THE ICONS ARE THE REFERENCE'S DEVICE AND THEY ARE NOT
                     DECORATION. Four bare phrases separated by pipes read as a
                     legal footer; the same four with a mark in front of each
                     read as four things the product does. Each shape says the
                     same thing as its words, so a reader who skips the type
                     still gets the row. --}}
                <ul class="rv-trustrail">
                    @foreach ([
                        ['gift', 'Free to join'],
                        ['shield', 'Everyone checked'],
                        ['rings', 'Soul mate first'],
                        ['lock', '18 and over'],
                    ] as [$icon, $label])
                        <li>
                            @include('public.partials.icon', ['name' => $icon, 'size' => 17])
                            <span>{{ $label }}</span>
                        </li>
                    @endforeach
                </ul>
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

    {{-- ══════════════════════════════════════════════════════════════════
         R9 · THE PAGE BELOW THE HERO

         Rebuilt to the client reference's COMPOSITION, in our own tokens and
         our own copy. Its proportions are what we took: a centred column with
         wide gutters, a brass eyebrow above every heading, one supporting
         paragraph at about sixty characters, then the grid.

         ⚠️ TWO OF THE REFERENCE'S LARGEST SECTIONS HAVE NO EQUIVALENT HERE.
         The twelve-sign wheel and the weighted-score breakdown were the
         product's headline feature in the version that reference was drawn
         for. They were removed from this product for religious reasons after a
         scholar was consulted. Nothing replaces them and the page is shorter
         than the reference by design. Do not fill the gap.
    ══════════════════════════════════════════════════════════════════ --}}

    {{-- ── 3 · The offer ─────────────────────────────────────────────────
         Reference composition: eyebrow with flanking rules, centred heading,
         one supporting line, three equal cards.

         ⚠️ FOUR CARDS BECAME THREE. The fourth named the delivery channel,
         which is a mechanism, and the reference's panel beneath enumerating
         photograph types is deleted outright rather than reworded. --}}
    <section class="rv-section rv-paper">
        <div class="rv-shell rv-shell--measure rv-stack" style="gap: 56px">
            <div class="rv-stack rv-section__head" data-reveal>
                <p class="rv-eyebrow rv-eyebrow--ruled">Why RealVows</p>
                <h2 class="rv-d2">What you are joining</h2>
                <p class="rv-lead rv-measure">
                    A small platform for people who have decided what they are looking for, and
                    would rather be introduced than go looking.
                </p>
            </div>

            {{-- ⚠️ EVERY DESCRIPTION ENDS THE SAME WAY. Four different endings
                 on one row is the sort of thing a reader feels without being
                 able to name it. --}}
            <div class="rv-grid rv-grid--3" data-stagger>
                @foreach ([
                    ['diamond', 'Free to join', 'Build your profile and be checked without paying anything.'],
                    ['check', 'Everyone is checked', 'Nobody reaches you unchecked, and nobody reaches you by accident.'],
                    ['four', 'Chosen, not searched', 'Introductions are brought to you, a few at a time, and always for both of you.'],
                ] as [$icon, $title, $body])
                    <article class="rv-card rv-offer__card rv-stack" style="gap: 20px" data-reveal>
                        <span class="rv-icon-disc">@include('public.partials.icon', ['name' => $icon])</span>
                        <h3 class="rv-d3">{{ $title }}</h3>
                        <p class="rv-body">{{ $body }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── 4 · What this is for ──────────────────────────────────────────
         This stands where the reference put its two largest sections and
         replaces neither. Only the asymmetric composition is taken: a taller
         editorial column, one quiet panel beside it.

         ⚠️ NOTHING HERE EXPLAINS HOW THE PRODUCT WORKS. No gauge, no score, no
         percentage, no numbered breakdown. If a sentence in this section
         describes a mechanism it is in the wrong section. --}}
    <section class="rv-section rv-tinted">
        <div class="rv-shell rv-shell--measure rv-purpose" data-reveal>
            <div class="rv-stack" style="gap: 24px">
                <p class="rv-eyebrow rv-eyebrow--ruled">Plainly</p>

                {{-- ⚠️ THE CLIENT'S OWN LINE. It replaced "RealVows is for
                     people ready to marry" at his direct instruction, so it is
                     not decorative and it is not reworded to fit a column.
                     ClientSuppliedCopyTest fails if it goes. --}}
                <h2 class="rv-d2 rv-measure--tight">
                    RealVows is for people looking to find their true soul mate.
                </h2>

                <p class="rv-body rv-measure">
                    It is not a dating application. There is no feed to scroll and nobody to
                    browse. You are introduced to a small number of people who genuinely fit,
                    and the intention is that you stop needing us.
                </p>

                <p class="rv-body rv-measure">
                    Everyone here has decided what they are looking for. That is the whole
                    filter, and it is the reason the introductions are worth reading.
                </p>
            </div>

            <aside class="rv-card rv-purpose__panel" data-reveal>
                <p class="rv-eyebrow">The standard</p>
                <p class="rv-vow rv-vow--panel">
                    Nobody here is passing the time.
                </p>
            </aside>
        </div>
    </section>

    {{-- ── 5 · How it works, in brief ────────────────────────────────────
         The reference's numbered rail, and the numbering carries real
         information because this genuinely is a sequence.

         ⚠️ THREE STEPS, NOT FOUR. The reference's fourth describes a
         verification method, and this product does not discuss its methods on
         a public page. --}}
    @include('public.partials.how-it-works')

    {{-- ── How the score is built ────────────────────────────────────────
         The client's reference puts a scoring breakdown here and asked for the
         same section "100% halal". Forty percent of theirs is derived from a
         date of birth. Ours reads the real weights out of config/matching.php,
         where that component no longer exists. See the partial for why the numbers are not typed. --}}
    @include('public.partials.how-we-score')

    {{-- ── 6 · What it costs ─────────────────────────────────────────────
         ⚠️ THE PRICING GRID IS NOT REPRODUCED, AND THE REASON IS A RULE
         RATHER THAN A PREFERENCE.

         Payments are not live: Payments::areLive() requires the free period to
         be off, a gateway configured, an environment that is not sandbox and
         merchant credentials present, and it currently answers no. A marketing
         page must never make a promise the database contradicts, which is the
         same rule that governs the founding count above.

         So the reference's three-card composition carries the free-period
         statement instead. --}}
    @unless (\App\Support\Payments::areLive())
        <section class="rv-section rv-paper">
            <div class="rv-shell rv-shell--measure rv-stack" style="gap: 56px">
                <div class="rv-stack rv-section__head" data-reveal>
                    <p class="rv-eyebrow rv-eyebrow--ruled">What it costs</p>
                    <h2 class="rv-d2">Nothing, while we are getting started</h2>
                    <p class="rv-lead rv-measure">
                        Creating an account, building your profile and being checked are free, and
                        they stay free.
                    </p>
                </div>

                <div class="rv-grid rv-grid--3" data-stagger>
                    @foreach ([
                        ['Joining', 'Free, and not for a trial period. Building a profile costs nothing at any point.'],
                        ['Being checked', 'Free. A real person reviews every member before anybody is introduced to them.'],
                        ['Introductions', 'Free while we are getting started. Anything you receive in that time stays open permanently.'],
                    ] as [$title, $body])
                        <article class="rv-card rv-offer__card rv-stack" style="gap: 16px" data-reveal>
                            <h3 class="rv-d3">{{ $title }}</h3>
                            <p class="rv-body">{{ $body }}</p>
                        </article>
                    @endforeach
                </div>

                {{-- ⚠️ ONE QUIET LINE ABOUT LATER, AND NO PROVIDER NAMED. --}}
                <p class="rv-small rv-centred" data-reveal>
                    When introductions do carry a cost, it will be a single payment for a set
                    number of them. There is no subscription and nothing renews on its own.
                </p>
            </div>
        </section>
    @endunless

    {{-- ── 7 · The closing invitation ────────────────────────────────────
         ⚠️ ONE BAND, ONE BUTTON, ONE LINE, USED ONCE AT THE END.

         This is the strongest thing in the reference and it suits us because
         we already own the arch. If a second call to action ever appears here,
         remove it rather than balancing it. --}}
    <section class="rv-panel rv-on-dark rv-invitation">
        <div class="rv-shell rv-shell--measure rv-stack rv-invitation__inner" data-reveal>
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
                <a href="{{ route('register') }}" class="rv-btn rv-btn--on-dark">Create your free account</a>
            </div>

            {{-- ⚠️ ONE LINE OF MICROCOPY, AS THE REFERENCE CLOSES. It states
                 what is true and asks for nothing further. If a second call to
                 action ever appears in this band, remove it rather than
                 balancing it. --}}
            <p class="rv-invitation__micro">Free to join · Everyone is checked · 18 and over</p>
        </div>
    </section>

    @include('public.partials.arrival')

@endsection
