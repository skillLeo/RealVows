@php($opening = $firstName ? "Hello $firstName," : 'Hello,')
{{ $opening }}

{{ $otherFirstName }} is interested in you as well.

You can now see how to reach each other. Whatever happens next is yours to
decide — we have done our part.

Their contact details are behind this link rather than in this email, because
an inbox is not a private place.

{{ route('dashboard') }}

@include('emails.text._footer')
