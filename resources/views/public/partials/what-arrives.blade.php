{{--
    ── Section 6: "What arrives" ─────────────────────────────────────────────

    Shows the product's own moment of value. Almost no marketing page shows
    the thing it is selling actually happening, and this will do more work
    than any headline above it.

    ⚠️ STATIC MARKUP, deliberately — not a live render of the real templates.
    A live render could drift into showing something the content rules exist
    to keep off a member's screen, and this is a page anyone can read without
    signing in.
--}}
<section class="rv-shell rv-section rv-paper" aria-labelledby="rv-arrives-heading">
    <div class="rv-stack rv-section__head" data-reveal>
        <p class="rv-eyebrow">The part that matters</p>
        <h2 class="rv-d2" id="rv-arrives-heading">What arrives</h2>
        <span class="rv-underline" style="max-width: 200px"></span>
        <p class="rv-lead" style="max-width: 52ch">
            You are not left refreshing a page. When we find someone, we bring them to you.
        </p>
    </div>

    <div class="rv-arrives" data-reveal>
        {{-- The email --}}
        <figure class="rv-arrives__card">
            <figcaption class="rv-eyebrow">By email</figcaption>

            <div class="rv-arrives__mock">
                <p class="rv-arrives__from">RealVows</p>
                <p class="rv-arrives__subject">Someone we would like you to meet</p>

                <span class="rv-arrives__rule" aria-hidden="true"></span>

                <p class="rv-arrives__score">92</p>
                <p class="rv-arrives__body">
                    A patient, home-building match with closely aligned family values.
                </p>
                <p class="rv-arrives__cta">Open this introduction</p>

                <span class="rv-seal rv-seal--sm rv-arrives__seal">RV</span>
            </div>
        </figure>

        {{-- The WhatsApp message --}}
        <figure class="rv-arrives__card">
            <figcaption class="rv-eyebrow">Or on WhatsApp</figcaption>

            <div class="rv-arrives__mock rv-arrives__mock--chat">
                <p class="rv-arrives__bubble">
                    Someone new is waiting for you on RealVows — 92 out of 100.
                    <br><br>
                    Open them whenever you are ready:<br>
                    <span class="rv-arrives__url">realvows.com/m/8f2a…</span>
                </p>
                <p class="rv-arrives__stamp">Delivered</p>
            </div>
        </figure>
    </div>

    <p class="rv-small rv-arrives__note" data-reveal>
        WhatsApp delivery arrives in a later release. Email is working today.
    </p>
</section>
