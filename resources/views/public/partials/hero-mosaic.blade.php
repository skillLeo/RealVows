{{--
    ── The hero arcade ───────────────────────────────────────────────────────

    Seven photographs in an arcade of offset columns. Placement lives entirely
    in CSS, keyed by the tile letter, because three named layouts cannot be
    expressed in an inline style. App\Support\Mosaic holds the same positions
    and MosaicTest fails if the two ever disagree.

    ⚠️ THE ARCH IS THE WHOLE IDEA, and it is not decoration.

    It is the one architectural form shared by both traditions this page
    speaks to — the mihrab arch and the nave arcade are the same gesture, a
    threshold you pass through to something serious. It costs a border-radius
    and it is the reason this reads as ceremonial rather than as a photo
    gallery.

    ⚠️ ONE role="img" FOR THE WHOLE THING, and empty alt on every tile.

    Seven decorative photographs with empty alt and no wrapper tells a screen
    reader user nothing is there. Seven descriptive alts is worse in practice:
    it reads seven near-identical sentences aloud before the visitor reaches
    the sign-up button. The arcade is one illustration, so it gets one label
    and its parts stay silent.

    ⚠️ The only inline values are the focal point, which is per photograph,
    and the motion indices, which are per tile. Neither can live in a
    stylesheet without one rule per file.
--}}
<div class="rv-mosaic" role="img"
     aria-label="Couples who met through RealVows">
    @foreach (\App\Support\Mosaic::tiles() as $tile)
        <figure class="rv-mosaic__tile rv-mosaic__tile--{{ $tile['key'] }} rv-mosaic__tile--{{ $tile['shape'] }}"
                style="--col: {{ $tile['column'] }}; --row: {{ $tile['row'] }}">
            @if ($tile['src'])
                <span class="rv-mosaic__plate">
                    <picture>
                        @if ($tile['webpset'])
                            <source type="image/webp"
                                    srcset="{{ $tile['webpset'] }}"
                                    sizes="{{ $tile['sizes'] }}">
                        @endif

                        <img src="{{ asset($tile['src']) }}"
                             @if ($tile['srcset'])
                                 srcset="{{ $tile['srcset'] }}"
                                 sizes="{{ $tile['sizes'] }}"
                             @endif
                             alt=""
                             style="object-position: {{ $tile['focal'] }}"
                             @if ($tile['eager'])
                                 fetchpriority="high"
                             @else
                                 loading="lazy" fetchpriority="low"
                             @endif
                             decoding="async">
                    </picture>
                </span>
            @else
                {{-- Sealed rather than wrong. A visibly empty frame says "not
                     finished"; a stand-in with the wrong content says
                     something about who this product is for. --}}
                <span class="rv-mosaic__plate rv-mosaic__plate--awaiting">
                    <span class="rv-seal" aria-hidden="true">RV</span>
                </span>
            @endif
        </figure>
    @endforeach
</div>

@if (\App\Support\Mosaic::awaitingRealPhotography())
    <span class="rv-pill rv-pill--on-dark rv-hero__note">Final photography to be added</span>
@endif
