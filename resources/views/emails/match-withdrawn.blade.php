@component('emails.layout', [
    'heading' => 'We have returned an introduction.',
    'preheader' => 'One of your matches is no longer available, and your credit is back.',
])
    <p style="margin:0 0 16px;">{{ $firstName ? "Hello $firstName," : 'Hello,' }}</p>

    <p style="margin:0 0 16px;">
        One of the people we introduced you to is no longer part of RealVows, so we have
        returned that introduction to your account. You can open someone else whenever you
        are ready.
    </p>

    <p style="margin:0 0 24px;">
        You do not need to do anything, and you have not lost anything. We would rather give
        the credit back than leave you with an introduction we can no longer stand behind.
    </p>

    @include('emails.partials.button', [
        'url' => route('dashboard'),
        'label' => 'Open your introductions',
    ])
@endcomponent
