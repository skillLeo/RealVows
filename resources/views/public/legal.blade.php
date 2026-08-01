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
