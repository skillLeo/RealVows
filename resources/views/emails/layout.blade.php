{{--
    The branded email frame: oxblood header band, brass rule, display-face
    heading, one clear button.

    Styles are inline and the layout is table-based on purpose. Email clients
    are twenty years behind browsers — Outlook still renders through Word —
    so the design system's tokens are written out as literal values here
    rather than as custom properties, which no mail client supports. This is
    the one place in the codebase where hard-coded colour is correct, and the
    values are copied from app.css.

    @param string $heading
    @param string|null $preheader
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;padding:0;background:#F2EFE9;color:#1F1518;font-family:'Inter',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;">

    {{-- Preview line in the inbox list, hidden in the body itself --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        {{ $preheader ?? $heading }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F2EFE9;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:560px;background:#F8F6F2;border:1px solid rgba(185,145,84,.4);border-radius:8px;overflow:hidden;">

                    {{-- Header band --}}
                    <tr>
                        <td style="background:#4A1220;padding:28px 32px;">
                            <span style="font-family:Georgia,'Times New Roman',serif;font-size:20px;line-height:1;letter-spacing:.02em;color:#B99154;">
                                RealVows
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td style="height:1px;background:#B99154;line-height:1px;font-size:0;">&nbsp;</td>
                    </tr>

                    <tr>
                        <td style="padding:40px 32px;">
                            <h1 style="margin:0 0 24px;font-family:Georgia,'Times New Roman',serif;font-size:28px;font-weight:400;line-height:1.25;color:#6B1834;">
                                {{ $heading }}
                            </h1>

                            {{ $slot }}
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 32px;">
                            <div style="height:1px;background:rgba(185,145,84,.2);font-size:0;line-height:1px;">&nbsp;</div>
                            <p style="margin:24px 0 0;font-size:14px;line-height:1.55;color:#6B5F62;">
                                RealVows · soul mate first · 18+<br>
                                If you weren't expecting this, you can ignore it safely.
                            </p>

                            {{--
                                ⚠️ THE CONTRACTING ENTITY, READ FROM SETTINGS.

                                Never a hardcoded string. "RealVows" could not be
                                registered as a company in time, so the business
                                that actually contracts with the member is a
                                different one — and an email footer naming the
                                wrong party is the same false statement as a
                                receipt naming the wrong party.

                                While it is unconfirmed this prints the pending
                                line rather than a guess. See Billing.php, which
                                explains at length why the entity has no default.
                            --}}
                            @php($billing = app(\App\Support\Billing::class))
                            <p style="margin:12px 0 0;font-size:12px;line-height:1.5;color:#8A7E81;">
                                @if ($billing->isConfigured())
                                    {{ $billing->tradingAsLine() ?? $billing->legalEntity() }}
                                    @if ($billing->address())
                                        <br>{{ $billing->address() }}
                                    @endif
                                @else
                                    The contracting entity is not yet confirmed.
                                @endif
                            </p>

                            {{--
                                ⚠️ UNSUBSCRIBE MUST WORK WITHOUT SIGNING IN.

                                A member who wants messages to stop is often one
                                who cannot or will not log in. A link behind
                                authentication is the commonest reason a
                                legitimate sender gets marked as spam, and a spam
                                complaint costs the whole domain's deliverability
                                rather than one message.

                                Absent rather than broken when no recipient is
                                known — a dead unsubscribe link is worse than
                                none, because it is the one link a annoyed member
                                definitely clicks.
                            --}}
                            @isset($unsubscribeUrl)
                                <p style="margin:12px 0 0;font-size:12px;line-height:1.5;color:#8A7E81;">
                                    <a href="{{ $unsubscribeUrl }}" style="color:#8A7E81;">
                                        Stop receiving these emails
                                    </a>
                                </p>
                            @endisset
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
