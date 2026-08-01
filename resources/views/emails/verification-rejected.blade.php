@component('emails.layout', [
    'heading' => 'One more try on your photos.',
    'preheader' => 'A small problem with your verification photo — it takes two minutes to fix.',
])
    <p style="margin:0 0 16px;">
        {{ $firstName ? "Hello $firstName," : 'Hello,' }}
    </p>

    <p style="margin:0 0 16px;">
        We could not confirm your verification photo this time. Nothing is wrong with your
        account and nothing you have written has been lost — this is usually a two-minute fix.
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

    <p style="margin:0 0 8px;font-size:14px;font-weight:500;letter-spacing:.14em;text-transform:uppercase;color:#6B5F62;">
        Your code
    </p>

    <p style="margin:0 0 24px;font-family:Georgia,'Times New Roman',serif;font-size:28px;line-height:1;letter-spacing:.12em;color:#6B1834;">
        {{ $code }}
    </p>

    <p style="margin:0 0 24px;">
        Take a new photo of yourself holding a handwritten note with that code, somewhere with
        good light, and upload it again. We will look at it as soon as we can.
    </p>

    @include('emails.partials.button', [
        'url' => route('profile.step', 'photos'),
        'label' => 'Upload a new photo',
    ])
@endcomponent
