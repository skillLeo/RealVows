{{-- ⚠️ Prose, not stripped HTML. A text part made by removing tags reads as
     debris, and a spam filter can tell the difference. --}}
@php
    $opening = $firstName ? "Hello $firstName," : 'Hello,';
    $found = $count === 1
        ? 'We have found someone we think you should meet.'
        : "We have found {$count} people we think you should meet.";
@endphp
{{ $opening }}

{{ $found }}
@if ($score)

Compatibility: {{ $score }} out of 100.
@endif
@if ($rationale)

{{ $rationale }}
@endif

Their name and photographs are waiting behind this link — we have kept them out
of this email, because an inbox is not a private place.

{{ route('dashboard') }}

@include('emails.text._footer')
