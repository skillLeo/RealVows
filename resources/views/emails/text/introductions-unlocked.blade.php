@php
    $opening = $firstName ? "Hello $firstName," : 'Hello,';
    $what = $count === 1 ? 'one introduction is' : "{$count} introductions are";
@endphp
{{ $opening }}

Your payment is complete, and {{ $what }} now open to read in full, with names,
photographs, and everything they chose to share.

Take your time. Nothing expires while it is open, and there is no queue behind you.

{{ route('dashboard') }}

@include('emails.text._footer')
