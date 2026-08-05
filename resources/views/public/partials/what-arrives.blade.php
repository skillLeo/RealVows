{{--
    ── Section 6: "What arrives" ─────────────────────────────────────────────

    Shows the product's own moment of value. Almost no marketing page answers
    "and then what happens?", and this section does more work than any headline
    above it — which is why it survives rather than being deleted.

    ⚠️ NO PREVIEW OF THE MESSAGE. DO NOT PUT THE MOCK-UPS BACK.

    This used to render a mocked-up email — sender line, subject, a
    compatibility score of 92, a rationale sentence, a call to action — and a
    mocked-up WhatsApp bubble with the message text and a link.

    The client asked for all of it to come off: "we should not tell them
    anything about what the email will say or what the WhatsApp message will
    say. Just tell them they will receive an email and a WhatsApp, but do not
    give them an idea what it will say or what it will look like."

    Two further reasons it should never come back, both discovered while
    removing it:

      · The mock carried the URL `realvows.com` — a domain nobody involved
        owns. It was live on this page for weeks. OutboundLinkTest now fails
        the build on any host outside the allow-list.

      · The mock led with a two-digit score. A number on a public page invites
        the question "how is it calculated", which is the one question the
        product does not answer.

    The emotional beat stays. The preview is gone.
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
        <figure class="rv-arrives__card">
            <figcaption class="rv-eyebrow">By email</figcaption>

            <div class="rv-arrives__mock">
                <p class="rv-arrives__body">
                    An introduction arrives in your inbox, privately, the moment it is ready for
                    you. Nobody else sees it and nothing is announced.
                </p>

                <span class="rv-seal rv-seal--sm rv-arrives__seal">RV</span>
            </div>
        </figure>

        <figure class="rv-arrives__card">
            <figcaption class="rv-eyebrow">And on WhatsApp</figcaption>

            <div class="rv-arrives__mock">
                <p class="rv-arrives__body">
                    So it reaches you wherever you are, without you sitting at a screen waiting
                    for it. Open it when the time is right.
                </p>
            </div>
        </figure>
    </div>

    {{--
        ⚠️ THIS LINE HAS AN EXPIRY AND SOMEBODY HAS TO REMOVE IT.

        The previous version read "WhatsApp delivery arrives in a later release.
        Email is working today" — written before WhatsApp existed and still on
        the live page long after it was built. It undersold the product on the
        page that sells it, and nothing in the build had any way to know.

        This replacement is honest as of today: the seven message templates are
        with Meta for review and WhatsApp delivery cannot switch on until they
        come back approved. The day they do, this line becomes the same kind of
        lie the last one was. It is carried as an owned item in
        LAUNCH-CHECKLIST.md so the removal is somebody's job rather than
        somebody's memory.
    --}}
    <p class="rv-small rv-arrives__note" data-reveal>
        Email introductions are working today. WhatsApp switches on as soon as Meta finishes
        reviewing our message templates.
    </p>
</section>
