@component('emails.layout', [
    'heading' => 'Reset your password.',
    'preheader' => 'A link to choose a new RealVows password. It expires in an hour.',
])
    <p style="margin:0 0 16px;">
        Somebody asked to reset the password on this RealVows account. If that was you, choose a
        new one here.
    </p>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Choose a new password'])

    <p style="margin:24px 0 0;">
        This link expires in {{ $expiresMinutes }} minutes. If you did not ask for it, nothing has
        changed and you can ignore this message.
    </p>

    <p style="margin:16px 0 0;font-size:14px;line-height:1.55;color:#6B5F62;">
        If the button does not work, copy this into your browser:<br>
        <span style="word-break:break-all;color:#C5A059;">{{ $url }}</span>
    </p>
@endcomponent
