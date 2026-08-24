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
        <p class="rv-eyebrow rv-eyebrow--ruled">Here is what we need from you</p>
        <{{ $heading }} class="rv-d2">How it works</{{ $heading }}>
        <span class="rv-underline" style="max-width: 220px"></span>
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
    <ol class="rv-process" data-stagger>
        @foreach ([
            ['01', 'Build your free profile', 'Guided the whole way, saved as you go. It costs nothing.'],
            ['02', 'Get verified', 'A real person confirms your identity before anyone is introduced to you.'],
            ['03', 'Receive your introductions', 'We choose them and bring them to you. There is nothing to search.'],
        ] as [$number, $title, $body])
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
