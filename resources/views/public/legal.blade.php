@extends('layouts.public')

@section('title', $heading)
@section('description', $intro)

@section('content')
    <article class="rv-shell rv-section rv-legal">
        <header class="rv-stack" style="gap: 16px">
            <span class="rv-label rv-label--brass">RealVows</span>
            <h1 class="rv-d1">{{ $heading }}</h1>
            <p class="rv-lead">{{ $intro }}</p>
        </header>

        {{--
            ⚠️ THE [DRAFT] BANNER IS GONE, AND THAT WAS THE CLIENT'S DECISION.

            It said the page was a placeholder awaiting his legal wording. He
            has now told us to write them instead: "the AI can give you a
            policy, ask Grok or somebody, they can give you the legal word for
            the policy." Leaving a placeholder notice on a page that is no
            longer a placeholder is its own kind of lie, and it is the sort of
            thing a careful reader notices and draws conclusions from.

            ⚠️ WHAT HAS NOT CHANGED: these are carefully drafted policies, not
            legal advice, and no lawyer in Jamaica has read them. That is
            recorded in LegalController's header and in the launch checklist so
            it stays a known fact rather than an assumption.

            ⚠️ THE [PENDING] ENTITY NOTICE BELOW STAYS. Only the client's bank
            can clear that one, and naming an unconfirmed company in a contract
            is worse than naming none.
        --}}
        @isset($sections)
            @foreach ($sections as $section)
                <h2 class="rv-d3">{{ $section['heading'] }}</h2>
                @foreach ($section['body'] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            @endforeach
        @endisset

        {{--
            ⚠️ WHO THE MEMBER IS CONTRACTING WITH, FROM THE SAME SETTING THE
            RECEIPT USES.

            "RealVows" is a trading name, not a legal person — the gateway
            account sits under a different company entirely. If this page
            names one party and the card statement names another, that is a
            mismatch an acquirer's underwriting will flag, and it is also just
            wrong.

            Nothing is printed until the client's bank confirms the entity.
            Naming an unconfirmed company in a contract is worse than naming
            none, because a member would be agreeing to terms with a party
            that may not be the one holding their money.
        --}}
        @if ($billing['legalEntity'])
            <div class="rv-legal__entity rv-stack" style="gap: 8px">
                <h2 class="rv-d3">Who you are contracting with</h2>
                <p class="rv-body">
                    {{ $billing['tradingName'] ? $billing['tradingAsLine'] ?? $billing['legalEntity'] : $billing['legalEntity'] }}
                    @if ($billing['statementLine'])
                        {{ $billing['statementLine'] }}
                    @endif
                </p>

                @if ($billing['address'])
                    <p class="rv-body">{{ $billing['address'] }}</p>
                @endif

                @if ($billing['supportEmail'])
                    <p class="rv-body">Billing enquiries: {{ $billing['supportEmail'] }}</p>
                @endif
            </div>
        @else
            {{-- ⚠️ STILL PENDING, AND STILL ONLY THE CLIENT'S BANK CAN CLEAR
                 IT. The bracketed all-caps label went because the rest of the
                 page no longer reads like a draft, and a shouted tag in the
                 middle of finished prose is exactly the seam a careful reader
                 notices. The fact it states has not changed. --}}
            <p class="rv-legal__draft">
                <strong>The contracting entity is not yet confirmed.</strong>
                The registered company behind RealVows is being confirmed with the bank. This
                section, and your receipt, will name it as soon as that is done.
            </p>
        @endif

        <div class="rv-stack" style="gap: 24px">
            <h2 class="rv-d3">Who this applies to</h2>

            {{-- ⚠️ THE CLIENT'S OWN SENTENCE, VERBATIM, INCLUDING THE LAST FOUR
                 WORDS. He wrote it out himself. Marriage is the destination and
                 it belongs at the end — do not "tidy" it away, and do not move
                 it forward. --}}
            <p class="rv-body">
                RealVows is for people aged 18 and over, who are seeking to find their true soul
                mate. Their synergy will help them develop that bond and a lasting relationship,
                leading to marriage. Creating an account and building a full profile is free.
            </p>

            {{--
                ⚠️ THE VERIFICATION METHOD IS NOT DESCRIBED HERE. DO NOT
                "COMPLETE" THIS SECTION.

                This paragraph used to explain exactly how identity is checked,
                down to the handwritten code. The client circled it and wrote
                "this is private information", and he is right for a stronger
                reason than presentation: publishing the anti-fraud method
                publishes what somebody needs in order to prepare for it. A
                check is more effective when its exact shape is not documented
                on a page anyone can read without signing in.

                The full explanation still exists, in the one place it belongs —
                inside the verification step itself, where it is an instruction
                to the person doing it rather than a disclosure to everyone
                else. See lang/en/realvows.php `profile.a_photo_of_you`.

                The outcome may be stated publicly. The mechanism may not.
            --}}
            <h2 class="rv-d3">Verification</h2>
            <p class="rv-body">
                Every member is checked before they can be matched.
            </p>

            {{--
                ⚠️ WHAT ARRIVES IS NOT DESCRIBED HERE EITHER.

                From the client: "we should not tell them anything about what
                the email will say or what the WhatsApp message will say. Just
                tell them they will receive an email and a WhatsApp, but do not
                give them an idea what it will say or what it will look like."

                The no-browsing sentence stays. That is product framing rather
                than delivery mechanics, and it is load-bearing.
            --}}
            <h2 class="rv-d3">How introductions reach you</h2>
            <p class="rv-body">
                RealVows chooses who you are introduced to, and introductions reach you by email
                and WhatsApp. There is no directory to browse and no way to search for other
                members.
            </p>

            <h2 class="rv-d3">Your data</h2>
            <p class="rv-body">
                Your last name stays private until you and another member have both expressed
                interest. You can ask us for a copy of your data, or ask us to delete it, at
                any time. How we hold it is set out in the Privacy Policy.
            </p>
        </div>
    </article>
@endsection
