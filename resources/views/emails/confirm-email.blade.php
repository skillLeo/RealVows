@component('emails.layout', [
    'heading' => 'One quick confirmation.',
    'preheader' => 'Confirm your email and your RealVows account is ready.',
])
    <p style="margin:0 0 16px;">
        Welcome to RealVows. Confirm this address and your account is ready — then you can start
        building your profile whenever you have a quiet ten minutes.
    </p>

    <p style="margin:0 0 24px;">
        Signing up and building your whole profile is free. You only choose a package when you
        would like us to introduce you to someone.
    </p>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Confirm my email'])

    <p style="margin:24px 0 0;font-size:14px;line-height:1.55;color:#6B5F62;">
        If the button does not work, copy this into your browser:<br>
        <span style="word-break:break-all;color:#B99154;">{{ $url }}</span>
    </p>
@endcomponent
