{{--
    ── Section 7: "This is not for everyone" ─────────────────────────────────

    The most honest section on the page, and the strongest signal to the
    people it IS for. Stating plainly who should leave is something only a
    product with a real position can afford to do.
--}}
<section class="rv-panel rv-notforeveryone rv-on-dark">
    <div class="rv-shell rv-notforeveryone__grid" data-reveal>
        <div class="rv-stack" style="gap: 16px">
            <p class="rv-eyebrow">Plainly</p>

            {{-- ⚠️ THE CLIENT'S OWN SENTENCE. He wrote it out himself after
                 seeing this band read "RealVows is for people ready to marry."
                 Keep his words; the register of this whole page is set here. --}}
            {{-- ⚠️ HIS WORDS. "looking to find" is very slightly redundant and
                 it is not ours to tighten. He wrote this sentence out himself
                 after seeing the old band, and a silent improvement to a line
                 the client authored is how a client stops trusting the copy he
                 approved. Leave it. --}}
            <h2 class="rv-d2" style="max-width: 20ch">
                RealVows is for people looking to find their true soul mate.
            </h2>
        </div>

        <div class="rv-stack" style="gap: 20px">
            {{-- ⚠️ THIS IS THE ONE PLACE ON THE HOME PAGE ALLOWED TO SAY WHAT
                 REALVOWS IS NOT.

                 The "not X, but Y" shape appeared four times on this page and
                 a habit reads as machine writing. It survives here because
                 "not a dating app" carries real product meaning and a reader
                 needs it early. The rest of the page now says what the product
                 IS, which takes more confidence anyway.

                 The old second sentence was three negatives in a row: nobody
                 to browse, nothing to swipe, no reason to be here. Rule of
                 three plus triple negation is the tic at its loudest. --}}
            <p class="rv-lead rv-notforeveryone__counter">
                It is not a dating app. There is no feed to scroll and nobody to browse. You are
                introduced to people, and that is the whole of it.
            </p>

            @include('public.partials.ornament', ['tone' => 'dark'])
        </div>
    </div>
</section>
