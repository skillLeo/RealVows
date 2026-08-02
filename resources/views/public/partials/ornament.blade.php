{{--
    The brass ornament.

    A wedding invitation is not typeset like a web page — it has a mark that
    separates one thought from the next, and that mark is what makes the page
    feel *set* rather than laid out. This is that mark: a hairline rule to each
    side of a small brass lozenge, repeated between sections so the whole page
    reads with one voice.

    Deliberately restrained. One shape, one weight, no colour beyond brass —
    an ornament that draws attention to itself is decoration, and decoration is
    what makes a premium page look cheap.

    @param string|null $tone   'dark' when it sits on an oxblood panel
    @param bool|null   $rules  false for the mark alone, with no hairlines

    ⚠️ Without its rules this is a fleuron rather than a separator — the same
    mark doing a different job. It fills a space; it does not divide one. Do
    not give it rules "for consistency": a rule reads as the end of a thought,
    and a fleuron dropped into empty space has no thought to end.
--}}
@php($tone = $tone ?? 'light')
@php($rules = $rules ?? true)

<div class="rv-ornament {{ $tone === 'dark' ? 'rv-ornament--dark' : '' }} {{ $rules ? '' : 'rv-ornament--mark' }}" aria-hidden="true">
    @if ($rules)
        <span class="rv-ornament__rule"></span>
    @endif

    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1" stroke-linecap="round" stroke-linejoin="round" focusable="false">
        {{-- A four-point star inside a diamond: the same geometry as the
             8px-radius language, rotated. --}}
        <path d="M12 2.5 21.5 12 12 21.5 2.5 12 12 2.5Z" opacity=".55" />
        <path d="M12 7.5c.7 2.6 1.2 3.1 3.8 3.8-2.6.7-3.1 1.2-3.8 3.8-.7-2.6-1.2-3.1-3.8-3.8 2.6-.7 3.1-1.2 3.8-3.8Z" />
    </svg>

    @if ($rules)
        <span class="rv-ornament__rule"></span>
    @endif
</div>
