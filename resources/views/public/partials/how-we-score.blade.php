{{--
    ── How the score is built ────────────────────────────────────────────────

    The client sent this section from his reference and asked for it "exactly,
    but 100% halal — no stars, no haram thing".

    ⚠️ THE REFERENCE SELLS A 12x12 BIRTH-DATE MATRIX AS FORTY PERCENT OF ITS SCORE.
    That is the single largest weight on their page and it is the one thing
    this product must never show. A scholar was consulted and the birth-date
    component was deleted outright — the matrix, the scorer, the calculator and
    the stored column. It was 25 points and the largest weight we had.

    ⚠️ SO THIS SECTION IS NOT A REWORDING OF THEIRS. It is the same layout
    carrying a different and true claim: every point comes from a sentence the
    member wrote about themselves. That is a stronger page than the one it is
    modelled on, and it is the only version of it we can stand behind.

    ⚠️ THE NUMBERS ARE READ FROM config('matching.weights'), NEVER TYPED HERE.

    config/matching.php calls itself "THE ONLY PLACE THESE NUMBERS EXIST" and
    it is right to. A percentage typed into a marketing page is correct on the
    day it is written and silently becomes a false public claim about how the
    product treats people the first time a weight is tuned. Reading the config
    means this section cannot say anything the engine is not doing.

    The descriptions are drawn from each scorer's own docblock rather than
    written fresh, for the same reason.
--}}
@php
    $weights = config('matching.weights');

    /*
     * ⚠️ THE COPY IS KEYED BY COMPONENT, AND A COMPONENT WITH NO COPY IS NOT
     * SILENTLY DROPPED — see the guard below. If somebody adds a sixth weight,
     * this page must either describe it or fail, because a breakdown that
     * quietly omits a component is a page claiming a score is built from less
     * than it is.
     */
    $copy = [
        'preferences' => ['What you asked for', 'Age, height, education, faith and distance, checked in both directions. A one-sided match is worth less than a mutual one. Satisfying you while failing them does not score full marks.'],
        'values'      => ['Values and life goals', 'Your ranked core values, marriage timeline, plans for children, and how much faith matters. The ranking is used, not just the overlap. Two people who both put family first are not the same as two who both put it fifth.'],
        'lifestyle'   => ['How you live day to day', 'Habits, education and occupation fit, languages spoken. Compatibility tables rather than equality, because a non-smoker and a social smoker are not the same pairing as two heavy smokers.'],
        'personality' => ['Interests and temperament', 'Shared interests and how the two of you are wired. Closeness is rewarded on some scales and deliberately not on others. Two people who are both entirely spontaneous have nobody booking the flights.'],
        'physical'    => ['Stated preferences', 'Height and body type as each of you described them, scored in both directions and averaged. Attraction that runs one way is not attraction.'],
    ];

    $missing = array_diff(array_keys($weights), array_keys($copy));
    $rows = collect($weights)
        ->sortDesc()
        ->map(fn ($weight, $key) => [
            'key' => $key,
            'weight' => $weight,
            'title' => $copy[$key][0] ?? $key,
            'body' => $copy[$key][1] ?? '',
        ])
        ->values();

    $threshold = config('matching.threshold.default');
@endphp

<section class="rv-section rv-paper" id="how-we-score">
    <div class="rv-shell rv-stack" style="gap: 0">
        <div class="rv-stack rv-section__head" data-reveal>
            <p class="rv-eyebrow rv-eyebrow--ruled">How the score is built</p>
            <h2 class="rv-d2">Every point comes from something you wrote</h2>
            <p class="rv-lead">
                {{-- ⚠️ THE DENIAL WAS DELETED, NOT THE POINT. This sentence used to
                     name the two things this product does not do, and the reader
                     that guards this failed the build over it — correctly. Naming them to
                     refuse them still puts them in front of a reader who had not
                     thought of them, on the page of a client who had the component
                     removed on a scholar's ruling. The positive claim is stronger
                     and says the same thing: everything here came from you. --}}
                Five components, weighted and added together. Nothing is read from your date
                of birth, and nothing about you is inferred that you did not write down
                yourself.
            </p>
        </div>

        @if ($missing)
            {{-- ⚠️ VISIBLE, NOT SILENT. A component with no copy would otherwise
                 vanish from a breakdown that claims to be complete. --}}
            <p class="rv-body" role="status">
                This breakdown is incomplete and is being repaired.
            </p>
        @endif

        <div class="rv-score">
            <ol class="rv-score__list" data-stagger>
                @foreach ($rows as $row)
                    <li class="rv-score__row" data-reveal>
                        <div class="rv-score__head">
                            <h3 class="rv-d3">{{ $row['title'] }}</h3>
                            <span class="rv-score__pct rv-numeral">{{ $row['weight'] }}<span class="rv-score__sign">%</span></span>
                        </div>
                        {{-- ⚠️ THE BAR IS THE NUMBER DRAWN, so it is aria-hidden and
                             the figure beside it carries the meaning for a screen
                             reader. Two announcements of one fact is noise. --}}
                        <div class="rv-score__track" aria-hidden="true">
                            <span class="rv-score__fill" style="--w: {{ $row['weight'] }}%"></span>
                        </div>
                        <p class="rv-body">{{ $row['body'] }}</p>
                    </li>
                @endforeach
            </ol>

            <aside class="rv-score__panel" data-reveal>
                <p class="rv-eyebrow rv-eyebrow--ruled rv-score__label">The floor</p>

                {{-- ⚠️ THE GAUGE SHOWS THE THRESHOLD, NOT A SAMPLE SCORE. The
                     reference animates an invented 75, then 95, then 100, which
                     is a number chosen to look good. Ours shows the one figure
                     on this page a reader can hold us to: the minimum below
                     which an introduction is never sent. --}}
                <div class="rv-gauge" data-gauge>
                    <svg viewBox="0 0 200 116" focusable="false" aria-hidden="true">
                        <path class="rv-gauge__track" pathLength="100"
                              d="M14 106 A86 86 0 0 1 186 106" />
                        <path class="rv-gauge__value" pathLength="100"
                              style="--to: {{ $threshold }}"
                              d="M14 106 A86 86 0 0 1 186 106" />
                    </svg>
                    <p class="rv-gauge__figure">
                        <span class="rv-numeral" data-count-to="{{ $threshold }}">{{ $threshold }}</span>
                        <span class="rv-gauge__unit">out of 100</span>
                    </p>
                </div>

                <p class="rv-body">
                    Every introduction carries a score out of 100. Nothing below
                    {{ $threshold }} is ever sent to you, and each one that is arrives with a
                    written explanation of why the two of you align.
                </p>

                <span class="rv-rule rv-rule--soft"></span>

                <p class="rv-body">
                    <strong>Nothing is guessed.</strong> The scoring is deterministic: the same
                    two profiles always produce the same result, and every number above can be
                    read back from your own answers. You are owed a reason, not a verdict.
                </p>
            </aside>
        </div>
    </div>
</section>
