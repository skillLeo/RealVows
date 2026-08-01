@component('emails.layout', [
    'heading' => "You're verified.",
    'preheader' => 'A real person has checked your profile. Welcome to RealVows.',
])
    <p style="margin:0 0 16px;">
        {{ $firstName ? "Hello $firstName," : 'Hello,' }}
    </p>

    <p style="margin:0 0 16px;">
        A real person has looked at your photos and confirmed you are who you say you are.
        Your profile is now part of the RealVows pool.
    </p>

    <p style="margin:0 0 24px;">
        From here, we do the looking. RealVows chooses the people you are most compatible with
        and brings them to you — there is nothing to search through.
    </p>

    @include('emails.partials.button', [
        'url' => route('dashboard'),
        'label' => 'Open your dashboard',
    ])
@endcomponent
