@component('emails.layout', [
    'heading' => 'Thank you.',
    'preheader' => 'Your receipt from RealVows, reference '.$reference,
])
    <p style="margin:0 0 16px;">
        {{ $firstName ? "Hello $firstName," : 'Hello,' }}
    </p>

    <p style="margin:0 0 16px;">
        Your payment has gone through and your introductions are open. This email is your
        receipt — keep it somewhere safe.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%"
           style="margin:0 0 24px; border:1px solid rgba(197, 160, 89,.4); border-radius:8px;">
        <tr>
            <td style="padding:16px 20px; border-bottom:1px solid rgba(197, 160, 89,.2);
                       font-family:Inter,Helvetica,Arial,sans-serif; font-size:14px; color:#6B5F62;">
                Reference
            </td>
            <td style="padding:16px 20px; border-bottom:1px solid rgba(197, 160, 89,.2); text-align:right;
                       font-family:Inter,Helvetica,Arial,sans-serif; font-size:14px; color:#1F1518;">
                {{ $reference }}
            </td>
        </tr>
        <tr>
            <td style="padding:16px 20px; border-bottom:1px solid rgba(197, 160, 89,.2);
                       font-family:Inter,Helvetica,Arial,sans-serif; font-size:14px; color:#6B5F62;">
                Introductions
            </td>
            <td style="padding:16px 20px; border-bottom:1px solid rgba(197, 160, 89,.2); text-align:right;
                       font-family:Inter,Helvetica,Arial,sans-serif; font-size:14px; color:#1F1518;">
                {{ $credits }}
            </td>
        </tr>
        @if ($paidOn)
            <tr>
                <td style="padding:16px 20px; border-bottom:1px solid rgba(197, 160, 89,.2);
                           font-family:Inter,Helvetica,Arial,sans-serif; font-size:14px; color:#6B5F62;">
                    Paid on
                </td>
                <td style="padding:16px 20px; border-bottom:1px solid rgba(197, 160, 89,.2); text-align:right;
                           font-family:Inter,Helvetica,Arial,sans-serif; font-size:14px; color:#1F1518;">
                    {{ $paidOn }}
                </td>
            </tr>
        @endif
        <tr>
            <td style="padding:16px 20px; font-family:Fraunces,Georgia,serif; font-size:16px; color:#6B1834;">
                Total
            </td>
            <td style="padding:16px 20px; text-align:right;
                       font-family:Fraunces,Georgia,serif; font-size:20px; color:#6B1834;">
                {{ $amount }}
            </td>
        </tr>
    </table>

    @include('emails.partials.button', [
        'url' => route('dashboard'),
        'label' => 'Open your introductions',
    ])

    <p style="margin:24px 0 0; font-family:Inter,Helvetica,Arial,sans-serif; font-size:14px; color:#6B5F62;">
        Your introductions do not expire. Open them whenever you are ready.
    </p>

    <p style="margin:12px 0 0; font-family:Inter,Helvetica,Arial,sans-serif; font-size:14px; color:#6B5F62;">
        <a href="{{ $receiptUrl }}" style="color:#7F5F2E;">View or print this receipt</a>
    </p>

    {{--
        ⚠️ THE DESCRIPTOR BELONGS IN THE EMAIL, NOT ONLY ON THE RECEIPT.

        This arrives days before the card statement does. Somebody who reads
        it here recognises the name later; somebody who only finds it by
        opening a receipt they never opened phones their bank instead.
    --}}
    @if ($billing['statementLine'])
        <p style="margin:24px 0 0; font-family:Inter,Helvetica,Arial,sans-serif; font-size:13px; color:#6B5F62;">
            {{ $billing['statementLine'] }}
            @if ($billing['tradingAsLine'])
                Payments are processed by {{ $billing['tradingAsLine'] }}.
            @endif
        </p>
    @endif

    @if ($billing['supportEmail'])
        <p style="margin:8px 0 0; font-family:Inter,Helvetica,Arial,sans-serif; font-size:13px; color:#6B5F62;">
            Questions about this charge: {{ $billing['supportEmail'] }}
        </p>
    @endif
@endcomponent
