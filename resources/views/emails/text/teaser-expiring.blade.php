@php
    $opening = $firstName ? "Hello $firstName," : 'Hello,';
    $what = $count === 1 ? 'One introduction is' : "{$count} introductions are";
    $when = $daysLeft === 1 ? 'a day' : "{$daysLeft} days";
@endphp
{{ $opening }}

{{ $what }} still waiting for you, and will close in {{ $when }}.

Introductions close so the people in them are not left waiting indefinitely.
If you would rather leave it, that is a perfectly good answer too.

{{ route('dashboard') }}

@include('emails.text._footer')
