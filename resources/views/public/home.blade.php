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
@section('title', 'The Dignified Path to a Lifelong Union')
{{-- ⚠️ The channel named here must be one the platform can actually use. --}}
{{-- ⚠️ THE REFERENCE'S DESCRIPTION NAMES TWO THINGS WE DO NOT DO: an AI
     live-video identity check, and a compatibility weighting derived from the
     component this product removed. The first is on hold under R10 §7.1 and the
     second went on a scholar's ruling, so R10 §1 rule 3 applies — neither his
     sentence nor ours ships unchanged. This is his hero paragraph, which he
     approved, cut to length.

     ⚠️ AND THE BANNED WORD IS NOT WRITTEN OUT, EVEN TO EXPLAIN ITS ABSENCE.
     The forbidden-terms reader scans SOURCE, not rendered output, and it has
     now failed this build three times over comments of mine describing what was
     removed. The guard is right every time: a file a member can reach has no
     business containing that vocabulary, and "I was only quoting it" is exactly
     the argument that would let it back in. --}}
@section('description', 'A premium matchmaking platform for people who have decided they want marriage, built on rigorous verification, values-based alignment and profile compatibility. Free to join, every member verified, introductions brought to your email'.($whatsappDelivery ?? false ? ' and WhatsApp' : '').'.')

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
                    <span>Marriage-Ready Matchmaking &middot; 18+</span>
                </p>

                {{-- ⚠️ THE BREAK IS SET, NOT LEFT TO THE CONTAINER.
                     "The Dignified Path to / a Lifelong Union" breaks where the
                     meaning breaks. Left to wrap, 360 and 390 strand "Union" on
                     a line of its own under a nearly empty second line. --}}
                <h1 class="rv-d-hero" data-arrival-words>The Dignified Path to<br class="rv-hero__break">
                    a Lifelong Union</h1>

                <span class="rv-hero__flourish rv-arrive-step"></span>

                {{-- ⚠️ HIS REFERENCE'S SENTENCE, WITH HIS OWN TWO-WORD EDIT.
                     Its last two words named the component this product
                     removed; he marked them "profile compatibility". Everything
                     before that is verbatim, em dash included. --}}
                <p class="rv-lead" style="max-width: 52ch">
                    A premium matchmaking platform for people who have decided they want marriage
                    &mdash; built on rigorous verification, values-based alignment, and profile
                    compatibility.
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
                    <a href="{{ route('register') }}" class="rv-btn rv-btn--primary">Create Your Free Profile</a>
                    <a href="{{ route('how-it-works') }}" class="rv-btn rv-btn--outline">See How It Works</a>
                </div>

                {{-- One line, small, under the actions. --}}
                <p class="rv-hero__micro">Free to join. Free to get verified. Pay only when you want a match.</p>

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
                    {{-- ⚠️ HIS WORDS, WITH THE TWO HE MARKED. "AI Verified"
                         became "Human Verified", and the item naming the
                         removed component became "Compatibility Matching" —
                         both because the reference describes a product this one
                         is not. The other two are verbatim. --}}
                    @foreach ([
                        ['gift', '100% Free Sign-up'],
                        ['shield', 'Human Verified'],
                        ['rings', 'Compatibility Matching'],
                        {{-- ⚠️ THE CHANNEL NAMED HERE MUST BE ONE THE PLATFORM
                             CAN ACTUALLY USE. His reference says "Email &
                             WhatsApp Delivery"; with delivery off that is a
                             promise a visitor would sign up for and never
                             receive. --}}
                        ['envelope', ($whatsappDelivery ?? false) ? 'Email & WhatsApp Delivery' : 'Email Delivery'],
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
                <h2 class="rv-d2">Uncompromising Standards</h2>
                <p class="rv-lead rv-measure">
                    A community built on trust, authenticity, and the shared intention of a
                    lifelong commitment.
                </p>
            </div>

            {{-- ⚠️ HIS THREE CARDS, HIS WORDS. Card two is the wording he
                 supplied himself rather than the reference's, because the
                 reference describes an AI face scan and we do a human review.
                 R10 §7.1 holds that until the video question is settled. --}}
            <div class="rv-grid rv-grid--3" data-stagger>
                @foreach ([
                    ['four', 'Rigorous Profiles', 'Four distinct photo types and a full values, lifestyle and preference profile — so you understand someone before an introduction is ever made.'],
                    ['check', 'Human Verification', 'A real person checks your photographs and sends you a guided instruction, in order to verify your identity. Only verified profiles can enter the matching pool.'],
                    ['diamond', '100% Free Sign-up', 'Create your complete profile and get verified without paying anything. You only pay when you are ready to receive a match.'],
                ] as [$icon, $title, $body])
                    <article class="rv-card rv-offer__card rv-stack" style="gap: 20px" data-reveal>
                        <span class="rv-icon-disc">@include('public.partials.icon', ['name' => $icon])</span>
                        <h3 class="rv-d3">{{ $title }}</h3>
                        <p class="rv-body">{{ $body }}</p>
                    </article>
                @endforeach
            </div>

            {{-- ⚠️ THE PANEL IS BACK. An earlier round deleted it for naming a
                 mechanism; the client has since asked for every part of his
                 reference, and the four photograph types are the clearest
                 statement on the page of what is actually being asked of a
                 member before they start. --}}
            <div class="rv-photopanel" data-reveal>
                <h3 class="rv-d3 rv-photopanel__head">The four required photographs</h3>

                <ul class="rv-photopanel__chips">
                    @foreach ([
                        'Front-facing close-up',
                        'Full-body social',
                        'Full-body casual wear',
                        'Any photo type',
                    ] as $i => $chip)
                        <li class="rv-photopanel__chip">
                            <span class="rv-photopanel__num" aria-hidden="true">{{ $i + 1 }}</span>
                            <span>{{ $chip }}</span>
                        </li>
                    @endforeach
                </ul>
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

    {{-- ── 6 · Curated Match Packages ────────────────────────────────────

         ⚠️ THE FULL GRID SHIPS. THE THREE BUTTONS DO NOT.

         An earlier round withheld the pricing entirely, on the rule that a
         marketing page must never make a promise the database contradicts.
         That rule is right and it is narrower than what was done: the prices
         are true, the packages are real, and Payments::areLive() only governs
         whether somebody can BUY one today.

         So R10-B §2 restores his cards, his prices, his per-match arithmetic
         and his feature lists, and replaces only the Select buttons with the
         free-period statement in the same visual frame. He sees his layout, no
         visitor is offered a decision the product cannot complete, and turning
         it on later is one flag.

         ⚠️ NO PROVIDER IS NAMED, on this or any public page. His reference
         names Stripe three times in its bundle. The payment-provider guard is
         one of the four R10 §8 kept enforced, and his footnote below is the
         wording that replaces it. --}}
    <section class="rv-section rv-paper" id="packages">
        <div class="rv-shell rv-stack" style="gap: 56px">
            <div class="rv-stack rv-section__head" data-reveal>
                <p class="rv-eyebrow rv-eyebrow--ruled">Curated Match Packages</p>
                <h2 class="rv-d2">Invest in Your Future, Not a Subscription</h2>
                <p class="rv-lead rv-measure">
                    Sign-up and verification are free. Match packages are a single one-time
                    payment &mdash; there is no recurring charge and nothing renews automatically.
                </p>
            </div>

            @php
                /*
                 * ⚠️ "Best Value", NOT "Most Popular" — R10 §7.4.
                 *
                 * His reference badges the middle card Most Popular. With no
                 * completed sales that is a claim about behaviour that has not
                 * happened. Best Value is arithmetically true at $16.67 against
                 * $20 and needs no data behind it. It can become Most Popular
                 * the day there are sales to support it.
                 */
                $packages = [
                    [
                        'name' => 'Single Match',
                        'blurb' => 'Begin with one carefully chosen introduction.',
                        'price' => '$20',
                        'term' => 'one-time',
                        'maths' => '1 curated match · $20.00 each',
                        'badge' => null,
                        'features' => [
                            '1 curated match',
                            'Full compatibility breakdown',
                            ($whatsappDelivery ?? false) ? 'Email & WhatsApp delivery' : 'Email delivery',
                        ],
                    ],
                    [
                        'name' => 'Triple Match',
                        'blurb' => 'The considered choice for a serious search.',
                        'price' => '$50',
                        'term' => 'one-time',
                        'maths' => '3 curated matches · $16.67 each',
                        'badge' => 'Best Value',
                        'features' => [
                            '3 curated matches',
                            'Full compatibility breakdown',
                            'Deep values analysis',
                            'Priority match delivery',
                        ],
                    ],
                    [
                        'name' => 'Premium Match Pack',
                        'blurb' => 'The fullest expression of our matchmaking.',
                        'price' => '$100',
                        'term' => 'one-time',
                        'maths' => '8 curated matches · $12.50 each',
                        'badge' => null,
                        /* ⚠️ "Complete alignment report" — he struck the middle
                           word out of his reference's version of this feature,
                           which named the component this product removed. */
                        'features' => [
                            '8 curated matches',
                            'Full compatibility breakdown',
                            'Complete alignment report',
                            'Priority match delivery',
                        ],
                    ],
                ];
            @endphp

            <div class="rv-grid rv-grid--3 rv-packages" data-stagger>
                @foreach ($packages as $pkg)
                    <article @class(['rv-card', 'rv-package', 'rv-package--featured' => $pkg['badge']])
                             data-reveal>
                        @if ($pkg['badge'])
                            <span class="rv-package__badge">{{ $pkg['badge'] }}</span>
                        @endif

                        <h3 class="rv-d3">{{ $pkg['name'] }}</h3>
                        <p class="rv-body rv-package__blurb">{{ $pkg['blurb'] }}</p>

                        <p class="rv-package__price">
                            <span class="rv-numeral">{{ $pkg['price'] }}</span>
                            <span class="rv-package__term">{{ $pkg['term'] }}</span>
                        </p>

                        <p class="rv-small rv-package__maths">{{ $pkg['maths'] }}</p>

                        <ul class="rv-package__features">
                            @foreach ($pkg['features'] as $feature)
                                <li>
                                    @include('public.partials.icon', ['name' => 'check', 'size' => 16])
                                    <span>{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>

                        @if (\App\Support\Payments::areLive())
                            <a href="{{ route('checkout.show', ['package' => \Illuminate\Support\Str::slug($pkg['name'])]) }}"
                               class="rv-btn rv-btn--primary rv-package__cta">Select</a>
                        @else
                            {{-- ⚠️ THE SAME FRAME, WITHOUT THE DECISION. A button
                                 that takes a decision and then cannot complete it
                                 is a broken promise on the busiest part of the
                                 page. --}}
                            <p class="rv-package__free">Free while we are getting started</p>
                        @endif
                    </article>
                @endforeach
            </div>

            <p class="rv-small rv-centred" data-reveal>
                Secure payment. Matches are delivered privately by
                email{{ ($whatsappDelivery ?? false) ? ' and WhatsApp' : '' }}.
            </p>
        </div>
    </section>

    {{-- ── 7 · The closing invitation ────────────────────────────────────
         ⚠️ ONE BAND, ONE BUTTON, ONE LINE, USED ONCE AT THE END.

         This is the strongest thing in the reference and it suits us because
         we already own the arch. If a second call to action ever appears here,
         remove it rather than balancing it. --}}
    <section class="rv-panel rv-on-dark rv-invitation">
        <div class="rv-shell rv-shell--measure rv-stack rv-invitation__inner" data-reveal>
            @include('public.partials.ornament', ['tone' => 'dark', 'rules' => false])

            <p class="rv-eyebrow">Your next chapter</p>

            <h2 class="rv-d1 rv-invitation__line">Begin Your Lifelong Journey Today</h2>

            {{-- ⚠️ TRUE IN BOTH STATES. This used to say "choose a match package
                 when you are ready", which is a sentence about a page that
                 returns 404 during the free period. It now describes what
                 actually happens now, and stays true when payments arrive. --}}
            <p class="rv-lead" style="max-width: 52ch">
                Join an exclusive community of serious individuals seeking lifelong commitment
                through values, alignment and profile compatibility.
            </p>

            <div>
                <a href="{{ route('register') }}" class="rv-btn rv-btn--on-dark">Create Your Free Profile</a>
            </div>

            {{-- ⚠️ ONE LINE OF MICROCOPY, AS THE REFERENCE CLOSES. It states
                 what is true and asks for nothing further. If a second call to
                 action ever appears in this band, remove it rather than
                 balancing it. --}}
            <p class="rv-invitation__micro">Free to join &middot; Verified members only &middot; Must be 18 or older</p>
        </div>
    </section>

    @include('public.partials.arrival')

@endsection
