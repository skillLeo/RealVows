{{--
    ⚠️ PLAIN TEXT, AND NO MARKUP AT ALL.

    This goes to whoever is on the other end of an alert at seven in the
    morning, possibly on a phone, possibly forwarded to somebody else. It
    carries no image, no logo and no tracking pixel — partly because
    EveryEmailIsAuditedTest forbids all three, and mostly because an
    operational alert that needs a stylesheet to be legible is one more thing
    to go wrong at the moment things are already going wrong.
--}}
{{ $intro }}

@foreach ($sections as $heading => $lines)
{{ strtoupper($heading) }}
{{ str_repeat('-', strlen($heading)) }}
@foreach ($lines as $line)
{{ $line }}
@endforeach

@endforeach
{{ $outro }}
