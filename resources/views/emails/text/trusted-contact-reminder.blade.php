{{ $memberName }} named you as her trusted contact on RealVows. She cannot be introduced to anybody until somebody who knows her confirms she is who she says she is.

Confirm you know her: {{ $acceptUrl }}

@if ($isFinal)
This is the last message we will send you. If you do nothing, we will let her know she can name somebody else, and you will not hear from us again.
@else
If you would rather not, you can ignore this. We will send one more reminder and then stop.
@endif
