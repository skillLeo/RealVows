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
                {{-- ⚠️ THIS SAYS THAT IT ARRIVES, NOT WHAT IS IN IT.
                     "Arrives in your inbox, privately, nobody else sees it" is
                     a description of the message, which the client asked to be
                     removed twice. It is also defensive: six sentences about
                     what the site prevents read as a product answering an
                     accusation nobody made. --}}
                <p class="rv-arrives__body">
                    It reaches you by email, quietly, whenever it is ready.
                </p>

                <span class="rv-seal rv-seal--sm rv-arrives__seal">RV</span>
            </div>
        </figure>

        {{--
            ⚠️ THE WHOLE CARD GOES, NOT JUST THE WORD.

            Softening the caption while leaving a second card standing would
            still show a visitor two places an introduction arrives. The card is
            the promise here, so the card is what is conditional.
        --}}
        @if ($whatsappDelivery ?? false)
            <figure class="rv-arrives__card">
                <figcaption class="rv-eyebrow">And on WhatsApp</figcaption>

                <div class="rv-arrives__mock">
                    <p class="rv-arrives__body">
                        And on your phone, wherever you are. Open it when the time is right.
                    </p>
                </div>
            </figure>
        @endif
    </div>

    {{--
        ⚠️ THE NOTE THAT EXPIRED, AND IT WENT STALE EXACTLY AS PREDICTED.

        It said WhatsApp "switches on as soon as Meta finishes reviewing our
        message templates". Meta finished. All six templates are approved and
        have been for some time. The sentence was written knowing it would
        become a lie and carried as somebody's job to remove, and it survived
        anyway — which is what usually happens to a line that depends on a
        person remembering.

        ⚠️ IT IS GONE RATHER THAN CORRECTED. A note explaining why a channel is
        missing only makes sense while the page is still promising that channel.
        The delivery flag now removes every mention of WhatsApp when it cannot
        send, so there is no gap left to explain and nothing here to go stale a
        second time. The truth is carried by a setting instead of a sentence.
    --}}
</section>
