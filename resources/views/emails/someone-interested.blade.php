{{--
    ⚠️ NOT ONE IDENTIFYING DETAIL. No name, no city, no score, no match id.

    The other member has said yes and this one has not answered. Naming them
    would disclose a one-sided interest the other person never agreed to make
    public — and would let a recipient work out who passed on them.
--}}
@component('emails.layout', [
    'heading' => 'Someone is interested in you.',
    'preheader' => 'One of your introductions has expressed interest.',
    'unsubscribeUrl' => $unsubscribeUrl ?? null,
])
    <p style="margin:0 0 16px;">{{ $firstName ? "Hello $firstName," : 'Hello,' }}</p>

    <p style="margin:0 0 16px;">
        Someone you were introduced to has expressed interest in you.
    </p>

    <p style="margin:0 0 24px;">
        We are not saying who. That is theirs to tell you, and it happens the moment
        you say yes too. Open your introductions whenever you are ready.
    </p>

    @include('emails.partials.button', ['url' => route('dashboard'), 'label' => 'Open your introductions'])
@endcomponent
