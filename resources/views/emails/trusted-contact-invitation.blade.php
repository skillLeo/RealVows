@component('emails.layout', ['heading' => $heading, 'preheader' => $preheader])
    <p style="margin:0 0 20px;">
        {{ $memberName }} has named you as her trusted contact on RealVows. She has told us you
        are her {{ strtolower($relationship) }}.
    </p>

    <p style="margin:0 0 20px;">
        RealVows introduces people who are looking for their soul mate. Before she can be
        introduced to anyone, we ask somebody who knows her to confirm she is who she says she
        is, and to look at each introduction before she does.
    </p>

    {{-- ⚠️ WHAT IS ASKED OF THEM, PLAINLY AND UP FRONT. Somebody deciding
         whether to accept needs to know what they are agreeing to before they
         click, not after they have created an account. --}}
    <p style="margin:0 0 20px;">
        If you accept, you will create your own account with your own password. You will be asked
        for one photograph of the two of you together, holding a short code we will show you.
        Nothing else is required of you.
    </p>

    <p style="margin:0 0 28px;">
        <a href="{{ $acceptUrl }}" style="display:inline-block;padding:14px 28px;background:#4A1220;color:#F8F6F2;border-radius:8px;text-decoration:none;">
            Accept and continue
        </a>
    </p>

    <p style="margin:0 0 20px;font-size:14px;color:#6B5F62;">
        This link works once and expires in {{ $expiresInDays }} days. If you were not expecting
        this, you can ignore it and nothing will happen.
    </p>
@endcomponent
