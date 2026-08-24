@extends('layouts.public')

@section('title', 'How it works')
@section('description', 'Build a profile, get verified, and receive introductions chosen for you.')

@section('content')
    <section class="rv-shell rv-shell--measure rv-section">
        <div class="rv-stack" style="gap: 16px" data-reveal>
            <span class="rv-label rv-label--brass">RealVows</span>
            {{-- ⚠️ THE PARAGRAPH THE CLIENT POINTED AT AND CALLED CONFUSING.

                 He was right, and the fault was grammatical rather than
                 stylistic. It read:

                   "You tell us who you are and what you are looking for, a
                    real person confirms you are genuine, and introductions
                    come to you."

                 Three independent clauses joined by commas. A comma splice
                 is confusing in a way a reader feels without being able to
                 name, because nothing tells them where one idea stops.

                 It is three sentences now, of deliberately different lengths.

                 The heading also lost its "not X" shape. That construction
                 appeared four times on the home page and once here; it now
                 appears once in the whole site, on the "not a dating app"
                 band, where it earns its place. --}}
            <h1 class="rv-d1" style="max-width: 20ch">We choose. You decide.</h1>
            <p class="rv-lead" style="max-width: 54ch">
                You will not spend your evenings scrolling. You tell us who you are and what
                matters to you. After that, introductions come to you.
            </p>
        </div>
    </section>

    {{-- The three steps, shared with the home page. --}}
    @include('public.partials.how-it-works', ['heading' => 'h2'])

    <section class="rv-shell rv-shell--measure rv-section rv-paper">
        <div class="rv-stack rv-section__head" data-reveal>
            <p class="rv-eyebrow rv-eyebrow--ruled">The part people ask about</p>
            <h2 class="rv-d2">How we choose</h2>
            <span class="rv-underline" style="max-width: 180px"></span>
        </div>

        {{--
            ⚠️ WHAT WE ASK FOR, NEVER HOW IT IS WEIGHED. The mechanism behind
            the score is the client's commercial secret and it stays internal.
            Everything on this page is true and is drawn from what members
            actually give us.
        --}}
        <div class="rv-stack" style="gap: 24px; max-width: 62ch" data-reveal>
            <p class="rv-body">
                You answer a set of questions about your values, your faith, the pace you want to
                move at and the life you are trying to build. The closest matches are brought to
                you a few at a time.
            </p>
            <p class="rv-body">
                What you will never do here is browse. A person you were introduced to deserves
                your attention more than a person you scrolled past.
            </p>
            <p class="rv-body">
                Your absolute limits are treated as absolute.
            </p>
        </div>
    </section>

    {{-- ⚠️ A WHOLE SECTION EXPLAINING VERIFICATION USED TO SIT HERE.

         It said every member is reviewed by a real person, that nobody enters
         the pool unverified, and that it is the slowest part of joining. All
         true, and all an explanation of how the product works rather than what
         it is for.

         The client has now said three times that the product should do things
         rather than describe how it does them, and publishing the shape of a
         check tells anyone who wants to work around it where to start. The
         outcome is stated once, in the three steps above. That is enough. --}}

    @include('public.partials.what-arrives')
@endsection
