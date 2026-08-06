{{--
    ⚠️ THE ONLY EMAIL THAT NAMES ANOTHER MEMBER, and only because both people
    have independently said yes. Still no photograph — the contact details are
    behind the link, not in the inbox.
--}}
@component('emails.layout', [
    'heading' => 'You are both interested.',
    'preheader' => 'You can now see how to get in touch.',
    'unsubscribeUrl' => $unsubscribeUrl ?? null,
])
    <p style="margin:0 0 16px;">{{ $firstName ? "Hello $firstName," : 'Hello,' }}</p>

    <p style="margin:0 0 16px;">
        {{ $otherFirstName }} is interested in you as well.
    </p>

    <p style="margin:0 0 24px;">
        You can now see how to reach each other. Whatever happens next is yours to decide.
        we have done our part.
    </p>

    <p style="margin:0 0 24px;color:#6B5F62;font-size:15px;">
        Their contact details are behind the link rather than in this email, because an inbox
        is not a private place.
    </p>

    @include('emails.partials.button', ['url' => route('dashboard'), 'label' => 'See how to get in touch'])
@endcomponent
