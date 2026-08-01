{{--
    Inline SVG icons for the Blade pages.

    Mirrors the shapes in resources/js/Components/RvIcon.vue. Not a font:
    the original design leaned on Noto Sans Symbols 2, a face that existed
    purely to draw glyphs that are no longer permitted anywhere a member can
    see — and dropping it also removes a render-blocking request from the
    page with the tightest budget in the product.

    @param string $name
--}}
@php($size = $size ?? 20)

<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">
    @switch($name)
        @case('diamond')
            <path d="M12 3 21 12l-9 9-9-9 9-9" />
            @break

        @case('check')
            <path d="m4.5 12.5 5 5 10-11" />
            @break

        @case('envelope')
            <rect x="2.5" y="5" width="19" height="14" rx="2" />
            <path d="m3 7 9 6 9-6" />
            @break

        @case('four')
            <path d="M14.5 19V5l-8 9.5h11" />
            @break

        @case('lock')
            <rect x="4.5" y="10.5" width="15" height="10" rx="2" />
            <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" />
            @break
    @endswitch
</svg>
