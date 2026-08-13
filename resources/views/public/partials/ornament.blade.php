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
        {{--
            ⚠️ A QUATREFOIL, NOT A STAR. THIS WAS A FOUR-POINT STAR AND IT
            BROKE THE PRODUCT'S OWN RULE.

            The design system bans star shapes outright — date-of-birth-derived
            matching was removed precisely so that idea could not creep
            back in as decoration — and then the separator ornament, which
            repeats on every marketing page, was a four-point star. The rule was
            being enforced everywhere except on the one mark that appears most.

            A quatrefoil is the correct replacement rather than merely a safe
            one. Four circles on a cross is a form BOTH traditions built for
            centuries: it is the Gothic window tracery of a cathedral and it is
            the rosette of an Islamic screen, and it carries no sky-borne
            reading in either. Same visual weight, same diamond footprint, none
            of the association.

            The lobes are semicircles: the chord from (12,4) to (20,12) is
            √128 ≈ 11.31, so r = 5.66 makes each arc exactly half a circle. A
            smaller radius would be silently scaled up by the renderer and the
            four lobes would stop matching.
        --}}
        <path d="M12 2.5 21.5 12 12 21.5 2.5 12 12 2.5Z" opacity=".55" />
        <path d="M12 4 A5.66 5.66 0 0 1 20 12 A5.66 5.66 0 0 1 12 20
                 A5.66 5.66 0 0 1 4 12 A5.66 5.66 0 0 1 12 4 Z" />
    </svg>

    @if ($rules)
        <span class="rv-ornament__rule"></span>
    @endif
</div>
