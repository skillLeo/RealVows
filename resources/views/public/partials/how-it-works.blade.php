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
    <div class="rv-stack rv-section__head" data-reveal>
        <p class="rv-eyebrow">Three steps, then we take over</p>
        <{{ $heading }} class="rv-d2">How it works</{{ $heading }}>
        <span class="rv-underline" style="max-width: 220px"></span>
    </div>

    <div class="rv-grid rv-grid--3" data-stagger>
        @foreach ([
            ['01', 'Build your profile', 'Seven guided steps, saved as you go'],
            ['02', 'Get verified', "A real person checks you're you, usually within a day"],
            ['03', 'Receive your introductions', 'Chosen for you and brought to you — there is nothing to search through'],
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
