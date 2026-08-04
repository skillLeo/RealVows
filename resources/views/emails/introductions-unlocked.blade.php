@component('emails.layout', [
    'heading' => 'Your introductions are open.',
    'preheader' => 'Your payment went through. Everything is ready to read.',
    'unsubscribeUrl' => $unsubscribeUrl ?? null,
])
    <p style="margin:0 0 16px;">{{ $firstName ? "Hello $firstName," : 'Hello,' }}</p>

    <p style="margin:0 0 16px;">
        Your payment is complete, and
        @if ($count === 1) one introduction is @else {{ $count }} introductions are @endif
        now open to read in full — names, photographs, and everything they chose to share.
    </p>

    <p style="margin:0 0 24px;color:#6B5F62;font-size:15px;">
        Take your time. Nothing expires while it is open, and there is no queue behind you.
    </p>

    @include('emails.partials.button', ['url' => route('dashboard'), 'label' => 'Read them now'])
@endcomponent
