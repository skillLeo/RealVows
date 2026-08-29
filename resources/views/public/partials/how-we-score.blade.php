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
    /*
     * ⚠️ THESE ARE THE REFERENCE'S OWN WORDS, VERBATIM — R10 §1 AND §5.
     *
     * R9 said take the composition and none of the wording. The client has
     * overruled that directly: "I do not want our words. I want the same words
     * that they have. Every single thing." So the strings below are copied
     * character for character from realvows.luxoraarts.com, ampersands and all,
     * and our house style on dashes and vocabulary is suspended here by §8.
     *
     * The one thing that is NOT theirs is the weighting. Their page describes
     * six dimensions, and its largest was the component this product removed.
     * Ours has five and the numbers come from config. Copy is theirs,
     * arithmetic is ours.
     */
    $copy = [
        'preferences' => ['Preference Fulfilment', 'Your stated preferences — age, height, education, faith and more — checked for mutual reciprocity.'],
        'values'      => ['Values & Life Goals', 'Core values overlap, marriage timeline, plans for children, and the importance of faith.'],
        'lifestyle'   => ['Lifestyle Compatibility', 'Daily habits, education and occupation fit, pets, and languages spoken.'],
        'personality' => ['Personality & Interests', 'Shared interests, proximity on personality scales, and signals drawn from your bio.'],
        'physical'    => ['Physical Preference', 'Mutual satisfaction of stated height and body type preferences.'],
    ];

    $missing = array_diff(array_keys($weights), array_keys($copy));
    $rows = collect($weights)
        ->sortDesc()
        ->map(fn ($weight, $key) => [
            'key' => $key,
            'weight' => $weight,
            'title' => $copy[$key][0] ?? $key,
            'body' => $copy[$key][1] ?? '',
            /*
             * ⚠️ DERIVED, NOT TYPED. The reference badges Preference Fulfilment
             * PRIMARY. Hardcoding that to one key would leave the badge sitting
             * on a component that is no longer the largest the moment anybody
             * retunes the weights — the exact class of drift this whole section
             * is built to prevent.
             */
            'primary' => $weight === max($weights),
        ])
        ->values();

    $threshold = config('matching.threshold.default');
@endphp

<section class="rv-section rv-paper" id="how-we-score">
    <div class="rv-shell rv-stack" style="gap: 0">
        <div class="rv-stack rv-section__head" data-reveal>
            <p class="rv-eyebrow rv-eyebrow--ruled">Our Secret Sauce</p>
            <h2 class="rv-d2">The Science of Harmony</h2>
            {{-- ⚠️ SIX BECAME FIVE, AND THE ENERGETIC LANGUAGE WENT WITH IT.

                 The reference reads "six dimensions" and describes its sixth in
                 the register this product removed on a scholar's ruling. Ours has
                 five, so this is one of the §5 marked changes rather than a
                 verbatim copy. Everything else in the sentence is his.

                 ⚠️ The forbidden-terms reader scans SOURCE, not just rendered
                 output, so naming that register even inside a Blade comment
                 fails the build. That is the guard working correctly: this file
                 is one a member can reach and the word has no business in it. --}}
            <p class="rv-lead">
                Our proprietary algorithm weighs five dimensions of compatibility, placing your
                most important preferences alongside the practical realities of building a life
                together.
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
                            @if ($row['primary'])
                                <span class="rv-score__badge">Primary</span>
                            @endif
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

                {{-- ⚠️ THE NUMBER IS INTERPOLATED, THE SENTENCE IS HIS.
                     PageMatchesEngineTest asserts this page states the threshold
                     the engine actually enforces, so the figure can never fall
                     out of step with config. --}}
                <p class="rv-body">
                    Every introduction carries a transparent score from 0 to 100. We only deliver
                    a match above a minimum threshold of {{ $threshold }} — and each one arrives
                    with a written explanation of exactly why the two of you align.
                </p>

                <span class="rv-rule rv-rule--soft"></span>

                {{-- Verbatim from the reference, §5 KEEP. --}}
                <p class="rv-body">
                    <strong>No black boxes.</strong> The scoring is deterministic and auditable.
                    The same two profiles will always produce the same result — because you
                    deserve a reason, not a guess.
                </p>
            </aside>
        </div>
    </div>
</section>
