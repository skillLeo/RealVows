{{--
    ⚠️ A DEADLINE STATED PLAINLY IS NOT A PRESSURE TACTIC — but it becomes one
    the moment it shouts. No countdown, no urgency colour, no exclamation.
--}}
@component('emails.layout', [
    'heading' => 'An introduction is about to close.',
    'preheader' => 'Still waiting, for a few more days.',
    'unsubscribeUrl' => $unsubscribeUrl ?? null,
])
    <p style="margin:0 0 16px;">{{ $firstName ? "Hello $firstName," : 'Hello,' }}</p>

    <p style="margin:0 0 16px;">
        @if ($count === 1)
            One introduction is
        @else
            {{ $count }} introductions are
        @endif
        still waiting for you, and will close in
        {{ $daysLeft === 1 ? 'a day' : "$daysLeft days" }}.
    </p>

    <p style="margin:0 0 24px;color:#6B5F62;font-size:15px;">
        Introductions close so the people in them are not left waiting indefinitely.
        If you would rather leave it, that is a perfectly good answer too.
    </p>

    @include('emails.partials.button', ['url' => route('dashboard'), 'label' => 'Have a look'])
@endcomponent
