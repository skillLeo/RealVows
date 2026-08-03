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

        <p class="rv-legal__draft">
            <strong>[DRAFT — pending client legal copy]</strong>
            This page is a placeholder. The final wording is being prepared and will replace
            everything below before launch.
        </p>

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
            <p class="rv-legal__draft">
                <strong>[PENDING — the contracting entity is not yet confirmed]</strong>
                The registered company behind RealVows is being confirmed with the bank. This
                section, and the receipt, will name it once it is.
            </p>
        @endif

        <div class="rv-stack" style="gap: 24px">
            <h2 class="rv-d3">Who this applies to</h2>
            <p class="rv-body">
                RealVows is for people aged 18 and over who are seriously seeking marriage.
                Creating an account and building a full profile is free.
            </p>

            <h2 class="rv-d3">Verification</h2>
            <p class="rv-body">
                Every member is reviewed by a real person before they can be matched. We ask for
                four photographs and one further photograph of you holding a handwritten note
                with a code we generate. That last photograph is used only to confirm you are
                who you say you are. It is never shown on your profile and never shown to
                another member.
            </p>

            <h2 class="rv-d3">How matches reach you</h2>
            <p class="rv-body">
                RealVows chooses your matches and delivers them to you by email and WhatsApp.
                There is no directory to browse and no way to search for other members.
            </p>

            <h2 class="rv-d3">Your data</h2>
            <p class="rv-body">
                Your photographs are held in private storage and are never publicly linkable.
                Your last name stays private until you and another member have both expressed
                interest. You can ask us for a copy of your data, or ask us to delete it, at
                any time.
            </p>
        </div>
    </article>
@endsection
