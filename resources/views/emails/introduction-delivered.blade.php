{{--
    The flagship email. A new introduction is waiting.

    ⚠️ NO PHOTOGRAPH OF THE OTHER MEMBER. NOT EVEN AN UNLOCKED ONE.

    Email is not a private channel. It sits in a preview pane on a shared
    laptop, on a lock screen at a family dinner, in a webmail account somebody
    else knows the password to. A locked introduction stays locked, and an
    unlocked one is unlocked behind the link rather than in the notification.

    A photograph in an email is also permanent in a way the product is not: a
    member who deletes their account cannot delete the copy sitting in fifty
    inboxes.

    ⚠️ THE RATIONALE IS PROFILE-DERIVED AND ARRIVES ALREADY WRITTEN. Nothing
    here composes a reason — it prints the one the engine produced, which
    ForbiddenLanguageTest already holds to the vocabulary rules.
--}}
@component('emails.layout', [
    'heading' => 'Someone new is waiting.',
    'preheader' => $count === 1
        ? 'One introduction, chosen for you.'
        : "{$count} introductions, chosen for you.",
    'unsubscribeUrl' => $unsubscribeUrl ?? null,
])
    <p style="margin:0 0 16px;">
        {{ $firstName ? "Hello $firstName," : 'Hello,' }}
    </p>

    <p style="margin:0 0 16px;">
        @if ($count === 1)
            We have found someone we think you should meet.
        @else
            We have found {{ $count }} people we think you should meet.
        @endif
    </p>

    @if ($score)
        {{--
            The score is in the email because it is the reason to open it, and
            because it is a fact about the pairing rather than about either
            person. Tabular numerals so it does not look like a price.
        --}}
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px;">
            <tr>
                <td style="padding:16px 20px;background:#F2EFE9;border:1px solid rgba(185,145,84,.4);border-radius:8px;">
                    <span style="font-family:Georgia,'Times New Roman',serif;font-size:32px;line-height:1;color:#6B1834;">{{ $score }}</span>
                    <span style="font-size:14px;color:#6B5F62;">&nbsp;out of 100</span>
                </td>
            </tr>
        </table>
    @endif

    @if ($rationale)
        <p style="margin:0 0 24px;">{{ $rationale }}</p>
    @endif

    <p style="margin:0 0 24px;color:#6B5F62;font-size:15px;">
        Their name and photographs are waiting behind the link — we have kept them
        out of this email, because an inbox is not a private place.
    </p>

    @include('emails.partials.button', [
        'url' => route('dashboard'),
        'label' => $count === 1 ? 'See who it is' : 'See who they are',
    ])
@endcomponent
