{{--
    ⚠️ SHE HAS ALREADY SAID YES. THIS IS NOT A CHASE.

    Nothing here counts down, nothing implies she is late, and nothing mentions
    the other person. Naming him would turn a reminder into pressure and hand
    her a detail the contact gate has not released.
--}}
@component('emails.layout', [
    'heading' => 'Tell us who you trust.',
    'preheader' => 'Your introduction is waiting on this one thing.',
    'unsubscribeUrl' => $unsubscribeUrl ?? null,
])
    <p style="margin:0 0 16px;">{{ $firstName ? "Hello $firstName," : 'Hello,' }}</p>

    <p style="margin:0 0 16px;">
        You accepted an introduction, and it is being held until you tell us who
        you trust to look at it with you.
    </p>

    <p style="margin:0 0 16px;">
        You can name someone close to you, or ask us to arrange somebody. Either
        answer is a good one, and nothing is asked of you beyond choosing.
    </p>

    @include('emails.partials.button', ['url' => route('trusted-contact.show'), 'label' => 'Tell us who you trust'])
@endcomponent
