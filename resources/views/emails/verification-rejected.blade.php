@component('emails.layout', [
    'heading' => 'One more try on your photos.',
    'preheader' => 'A small problem with your verification photo. It takes two minutes to fix.',
])
    <p style="margin:0 0 16px;">
        {{ $firstName ? "Hello $firstName," : 'Hello,' }}
    </p>

    <p style="margin:0 0 16px;">
        We could not confirm your verification photo this time. Nothing is wrong with your
        account and nothing you have written has been lost. This takes about two minutes to put right.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 24px;border:1px solid rgba(185,145,84,.4);border-radius:8px;background:#F2EFE9;">
        <tr>
            <td style="padding:20px 24px;">
                <p style="margin:0 0 8px;font-size:14px;font-weight:500;letter-spacing:.14em;text-transform:uppercase;color:#6B5F62;">
                    What we saw
                </p>
                <p style="margin:0;font-size:16px;line-height:1.6;color:#1F1518;">{{ $reason }}</p>

                @if ($note)
                    <p style="margin:12px 0 0;font-size:14px;line-height:1.55;color:#6B5F62;">{{ $note }}</p>
                @endif
            </td>
        </tr>
    </table>

    {{--
        ⚠️ THE CODE AND THE INSTRUCTIONS ARE BOTH GONE FROM THIS EMAIL, AND
        THEY WERE HERE TOGETHER.

        This printed the live code in large type and then, one paragraph below,
        described the method in full: take a photo holding a handwritten note
        with that code. The two halves side by side are a complete recipe, in
        the single message a member is most likely to forward to somebody
        helping them, screenshot, or leave open on a shared screen.

        The method only works while it is not known in advance. Every public
        page in this product is already forbidden from describing it, and an
        email travels further than a page — it is quoted, printed, and sits in
        an inbox that outlives the account.

        Both live on the page behind the button, which requires signing in. That
        is the correct home for a live credential and for the one instruction
        that gives it meaning. This email says what happened and what to do
        next, which is all a notification is for.
    --}}
    <p style="margin:0 0 24px;">
        Sign in and follow the steps on your photos page to send us a new one. We will look
        at it as soon as we can.
    </p>

    @include('emails.partials.button', [
        'url' => route('profile.step', 'photos'),
        'label' => 'Upload a new photo',
    ])
@endcomponent
