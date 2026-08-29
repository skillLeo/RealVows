{{--
    ── The three steps ───────────────────────────────────────────────────────

    ⚠️ ONE SOURCE, TWO PLACEMENTS. DO NOT COPY THIS INTO THE PAGE.

    This is included by the home page AND by /how-it-works. The client asked for
    a navigation link to "how it works", and the obvious way to build that page
    is to paste the section into it — which gives two pieces of copy that agree
    on the day they are written and drift from then on. The one that gets edited
    is whichever one the next person happens to open.

    `$heading` lets the standalone page carry an <h1> while the home page keeps
    an <h2>, because a page with no h1 and a page with two are both wrong.
--}}
@php
    $heading ??= 'h2';
@endphp

<section class="rv-shell rv-section rv-paper">
    {{-- ⚠️ THE CLIENT'S FRAMING. He wrote "Here's what we require from your
         end" against this section, meaning he wants it read as what is being
         asked of the member rather than as a list of features.

         "Require" is the language of a form. This is the moment somebody
         decides whether to begin, so "need" is the warmer and more accurate
         word, and the contraction goes because the marketing pages do not use
         them. --}}
    <div class="rv-stack rv-section__head" data-reveal>
        <p class="rv-eyebrow rv-eyebrow--ruled">The Process</p>
        <{{ $heading }} class="rv-d2">A Refined, Discreet Path to Your Life Partner</{{ $heading }}>
        <span class="rv-underline" style="max-width: 220px"></span>
        <p class="rv-lead rv-measure">
            Four deliberate steps. No swiping, no endless browsing, no performance &mdash; only
            considered introductions.
        </p>
    </div>

    {{-- ⚠️ Every description is a full sentence ending in a full stop. They
         used to be unpunctuated fragments sitting beside punctuated prose.

         Step 02 read "A real person checks you're you, usually within a day".
         That is a tautology, a contraction against register, and a hedge on a
         promise, in nine words. --}}
    {{-- ⚠️ A RAIL, NOT THREE CARDS. In the reference the steps are drawn as
         markers threaded on one horizontal line, which says "these happen in
         this order" before a single word is read. Three separate boxes say
         "here are three things" and leave the sequence to the numerals alone.

         ⚠️ THE LINE IS BUILT FROM TWO HALF-SEGMENTS PER STEP, not one line
         behind the row. A single full-width rule has to be inset by half a
         column at each end or it runs out past the first and last markers into
         nothing, and that inset is a magic number that breaks the moment the
         step count changes. Each marker owns the track to its left and the
         track to its right; the first drops its left, the last drops its
         right. Add a fourth step and it still closes correctly. --}}
    {{-- ⚠️ THREE AND FOUR ARE SWAPPED FROM THE REFERENCE, ON HIS INSTRUCTION.

         Its order is Create, Verify, Choose a Package, Receive Your Matches.
         He wants the matches before the money: you see what arrives, and only
         then are you asked to pay for it. The markers are renumbered rather
         than the text merely reordered, so 03 is Receive and 04 is Choose.

         ⚠️ THE NUMBERS ARE DERIVED FROM POSITION, NOT TYPED.
         Written by hand, a future reorder produces a rail reading 01 02 04 03
         and nothing catches it. --}}
    @php
        /*
         * ⚠️ A PHP COMMENT, NOT A BLADE ONE, AND THE STEPS LIVE IN AN @php
         * BLOCK RATHER THAN IN THE @foreach EXPRESSION.
         *
         * Written as {{-- --}} inside the array literal these are not comments
         * at all — Blade compiles the directive's argument as PHP, so the
         * markers land mid-expression and the page 500s. how-we-score carries a
         * note about the same trap and I walked into it anyway.
         *
         * ⚠️ TODO — R10 §7.1, step 02. The reference says the member records a
         * short live video and we match the face to their photographs. This
         * product does not: video capture was removed months ago at the
         * client's own request and replaced with the guided instruction and a
         * human review queue. He has since said he is open to video, but that
         * is a build decision waiting on a scholar's answer and on scope, so
         * the wording below describes what actually happens today. One edit
         * when the answer comes back.
         */
        $steps = [
            ['Create Your Profile', 'Work through a guided multi-step form covering values, lifestyle, marriage goals and partner preferences. Drafts save as you go.'],
            ['Verify Your Identity', 'A real person checks your photographs and sends you a guided instruction, so we can confirm you are who you say you are.'],
            /*
             * ⚠️ THE CHANNEL IS CONDITIONAL, THE SENTENCE IS HIS.
             *
             * His reference reads "arrive by email and WhatsApp". WhatsApp
             * delivery is a setting, and while it is off that sentence is a
             * promise a visitor would sign up for and never receive — which is
             * the one thing a marketing page may never do. When it is on, his
             * wording is restored exactly.
             */
            ['Receive Your Matches', 'Curated introductions arrive by email'
                .(($whatsappDelivery ?? false) ? ' and WhatsApp' : '')
                .', each with a full compatibility breakdown and a secure profile link.'],
            ['Choose a Package', 'Select a one-time match package. No subscription, no auto-renewal, no hidden charges — ever.'],
        ];
    @endphp

    <ol class="rv-process" data-stagger>
        @foreach ($steps as $i => [$title, $body])
            @php ($number = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT))
            <li class="rv-process__step" data-reveal>
                <span class="rv-process__rail" aria-hidden="true">
                    <span class="rv-process__marker rv-numeral">{{ $number }}</span>
                </span>
                <h3 class="rv-d3">{{ $title }}</h3>
                <p class="rv-body">{{ $body }}</p>
            </li>
        @endforeach
    </ol>
</section>
