@component('emails.layout', ['heading' => $heading, 'preheader' => $preheader])
    <p style="margin:0 0 20px;">
        {{ $memberName }} named you as her trusted contact on RealVows. She cannot be introduced
        to anybody until somebody who knows her confirms she is who she says she is.
    </p>

    <p style="margin:0 0 28px;">
        <a href="{{ $acceptUrl }}" style="display:inline-block;padding:14px 28px;background:#4A1220;color:#F8F6F2;border-radius:8px;text-decoration:none;">
            Confirm you know her
        </a>
    </p>

    {{-- ⚠️ HOW TO STOP, WITHOUT AN UNSUBSCRIBE LINK. The schedule genuinely
         stops at two reminders, so this is a true instruction rather than a
         link implying a subscription nobody agreed to. --}}
    <p style="margin:0 0 20px;font-size:14px;color:#6B5F62;">
        @if ($isFinal)
            This is the last message we will send you. If you do nothing, we will let her know she
            can name somebody else, and you will not hear from us again.
        @else
            If you would rather not, you can ignore this. We will send one more reminder and then
            stop.
        @endif
    </p>
@endcomponent
