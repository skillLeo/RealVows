@extends('layouts.public')

@section('title', 'Questions')
@section('description', 'What RealVows is for, what it costs, and what happens after you join.')

@section('content')
    {{--
        ⚠️ NATIVE <details>, NOT A JAVASCRIPT ACCORDION.

        The public pages ship no Vue and this one must not be the exception. A
        native disclosure widget is keyboard operable, announced correctly by a
        screen reader, works with JavaScript disabled, and is findable by the
        browser's own in-page search — which a JS accordion collapses and hides.

        ⚠️ THE ANSWERS DESCRIBE OUTCOMES, NEVER MECHANISMS. Consistent with the
        rest of the public copy: no verification steps, no description of what a
        message contains, no list of controls. A FAQ is exactly where somebody
        would try to be helpful and explain how the check works.
    --}}
    <section class="rv-shell rv-section">
        <div class="rv-stack" style="gap: 16px" data-reveal>
            <span class="rv-label rv-label--brass">RealVows</span>
            <h1 class="rv-d1" style="max-width: 18ch">Questions</h1>
            <p class="rv-lead" style="max-width: 54ch">
                The things people ask before they join.
            </p>
        </div>
    </section>

    <section class="rv-shell rv-section rv-paper">
        <div class="rv-faq" data-reveal>
            @foreach ([
                ['What is RealVows for?',
                 'Finding your soul mate. It is not a dating app and there is nothing to browse. You are introduced to a small number of people who genuinely fit, and the intention is that you stop needing us.'],

                ['What does it cost?',
                 'Joining is free and building your whole profile is free. Introductions are free while we are getting started, and anything you receive in that time stays yours permanently.'],

                ['How do I meet somebody?',
                 'You do not search. We choose who you are introduced to and bring them to you by email and WhatsApp. There is no directory and no way to look somebody up.'],

                ['Is everyone here real?',
                 'Everyone is checked before they can be matched. Nobody enters the matching pool unchecked.'],

                ['Who can see my photographs?',
                 'Only somebody you have been introduced to. They are never public and never findable by a search engine.'],

                ['When does somebody learn my last name?',
                 'When you have both said you are interested. Not before, and not if only one of you has.'],

                ['Can I change my mind about somebody?',
                 'You can say no to any introduction and you never have to give a reason. If you would rather never see a particular person again, you can say so and they are never told.'],

                ['How long does it take to be approved?',
                 'Usually about a day. It is the slowest part of joining and the reason the rest of it is worth anything.'],

                ['What if I want to stop?',
                 'Close your account at any time from your account settings. We delete your profile, your photographs and your introductions.'],

                ['Something has gone wrong. Who do I talk to?',
                 'Write to us from the support page inside your account. A person reads every message and replies there.'],
            ] as [$question, $answer])
                <details class="rv-faq__item">
                    <summary class="rv-faq__q">{{ $question }}</summary>
                    <p class="rv-faq__a">{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    </section>
@endsection
