{{--
    Unsubscribing, from a mail client, with no session.

    Plain Blade rather than Inertia on purpose: this page must render for
    somebody who is not signed in, possibly on a slow connection, possibly
    inside a webmail preview pane. Loading a JavaScript application to switch
    off a boolean would be the wrong trade in every one of those cases.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Notification settings — RealVows</title>
    <style>
        body { margin:0; background:#F2EFE9; color:#1F1518;
               font-family:'Inter',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;
               font-size:16px; line-height:1.6; }
        .rv-card { max-width:520px; margin:48px auto; background:#F8F6F2;
                   border:1px solid rgba(197, 160, 89,.4); border-radius:8px; overflow:hidden; }
        .rv-band { background:#4A1220; padding:24px 32px;
                   font-family:Georgia,'Times New Roman',serif; font-size:20px; color:#C5A059; }
        .rv-body { padding:32px; }
        h1 { margin:0 0 16px; font-family:Georgia,'Times New Roman',serif;
             font-size:26px; font-weight:400; color:#6B1834; }
        .rv-button { display:inline-block; margin-top:24px; padding:14px 28px;
                     background:#6B1834; color:#F8F6F2; border:0; border-radius:8px;
                     font-size:16px; cursor:pointer; text-decoration:none; }
        .rv-quiet { color:#6B5F62; font-size:14px; }
    </style>
</head>
<body>
    <div class="rv-card">
        <div class="rv-band">RealVows</div>
        <div class="rv-body">

            @if (! empty($done))
                <h1>That is done.</h1>
                <p>
                    You will no longer receive {{ $channel === 'whatsapp' ? 'WhatsApp messages' : 'emails' }}
                    about new introductions.
                </p>

                {{--
                    ⚠️ SAYING WHAT IS STILL ON IS NOT AN UPSELL.

                    Opting out of one channel leaves the other running, and a
                    member who believes they have switched everything off and
                    then hears from us reads that as being ignored. Telling
                    them plainly is what makes the per-channel rule honest
                    rather than a technicality.
                --}}
                <p class="rv-quiet">
                    {{ $channel === 'whatsapp' ? 'Email' : 'WhatsApp' }} notifications are unaffected.
                    You can change either from your account settings whenever you like.
                </p>

            @elseif ($already)
                <h1>Already switched off.</h1>
                <p>
                    You are not receiving {{ $channel === 'whatsapp' ? 'WhatsApp messages' : 'emails' }}
                    about new introductions. Nothing more to do.
                </p>

            @else
                <h1>Stop these messages?</h1>
                <p>
                    We will stop sending {{ $channel === 'whatsapp' ? 'WhatsApp messages' : 'emails' }}
                    about new introductions to {{ $channel === 'whatsapp' ? $user->fullPhone() : $user->email }}.
                </p>

                {{--
                    ⚠️ THE ACT IS THIS POST, NOT THE LINK THAT ARRIVED HERE.

                    Mail clients and corporate scanners follow every link in an
                    email before a human sees it. A GET that unsubscribed would
                    switch members off silently, and nobody would ever connect
                    it to the scanner that did it.
                --}}
                <form method="POST" action="{{ $confirmUrl }}">
                    <button type="submit" class="rv-button">Yes, stop them</button>
                </form>

                <p class="rv-quiet" style="margin-top:24px;">
                    Your introductions stay in your account either way — this only
                    changes how we let you know about them.
                </p>
            @endif

        </div>
    </div>
</body>
</html>
