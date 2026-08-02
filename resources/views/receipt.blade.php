<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Receipt {{ $purchase->reference() }} · RealVows</title>

    {{-- Inlined, because this page is printed and often opened from an email
         client that will not fetch a stylesheet. --}}
    <style>
        :root {
            --oxblood-deep: #4A1220; --oxblood: #6B1834; --brass: #B99154;
            --brass-ink: #7F5F2E; --cream: #F2EFE9; --card: #F8F6F2;
            --ink: #1F1518; --ink-soft: #6B5F62; --hairline: rgba(185,145,84,.4);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 40px 20px; background: var(--cream); color: var(--ink);
            font-family: Inter, system-ui, -apple-system, sans-serif;
            font-size: 16px; line-height: 1.6;
        }
        .sheet {
            max-width: 640px; margin: 0 auto; padding: 40px;
            background: var(--card); border: 1px solid var(--hairline); border-radius: 8px;
        }
        .head { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; }
        .mark {
            width: 44px; height: 44px; display: grid; place-items: center;
            border: 1px solid var(--brass); border-radius: 8px;
            font-family: Fraunces, Georgia, serif; font-size: 20px; color: var(--brass-ink);
        }
        h1 {
            margin: 24px 0 4px; font-family: Fraunces, Georgia, serif; font-weight: 400;
            font-size: 28px; line-height: 1.25; color: var(--oxblood);
        }
        .ref { margin: 0; font-size: 14px; color: var(--ink-soft); }
        .rule { height: 1px; background: var(--hairline); margin: 28px 0; border: 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 12px 0; vertical-align: top; }
        td.label { color: var(--ink-soft); font-size: 14px; }
        td.value { text-align: right; }
        .total td {
            padding-top: 20px; font-family: Fraunces, Georgia, serif;
            font-size: 20px; color: var(--oxblood);
        }
        .billed { font-size: 14px; color: var(--ink-soft); }
        .billed strong { color: var(--ink); font-weight: 600; }
        .foot { margin-top: 32px; font-size: 14px; color: var(--ink-soft); }

        /* The seal, quiet, bottom right — the same disc geometry as the mark. */
        .seal {
            width: 32px; height: 32px; margin-left: auto; margin-top: 24px;
            display: grid; place-items: center; border-radius: 50%;
            border: 1px solid var(--brass);
            font-family: Fraunces, Georgia, serif; font-size: 12px; color: var(--brass-ink);
        }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { border: 0; max-width: none; padding: 0; background: #fff; }
            .noprint { display: none; }
        }
        .noprint button {
            min-height: 44px; padding: 0 20px; border: 1px solid var(--hairline);
            border-radius: 8px; background: var(--oxblood); color: var(--cream);
            font: inherit; font-weight: 600; cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="head">
            <span class="mark" aria-hidden="true">RV</span>
            {{--
                ⚠️ NOTHING IS PRINTED UNTIL THE ENTITY IS CONFIRMED.

                A receipt naming the wrong company is worse than one naming
                none — it is a false statement about who is holding somebody's
                money. The fields are empty until the client's bank confirms
                which business the gateway account sits under.
            --}}
            <div style="text-align:right">
                @if ($billing['tradingAsLine'])
                    <div class="billed"><strong>{{ $billing['legalEntity'] }}</strong></div>
                    <div class="billed">trading as {{ $billing['tradingName'] }}</div>
                @elseif ($billing['legalEntity'])
                    <div class="billed"><strong>{{ $billing['legalEntity'] }}</strong></div>
                @endif
            </div>
        </div>

        <h1>Receipt</h1>
        <p class="ref">{{ $purchase->reference() }}</p>

        <hr class="rule">

        <table>
            <tr>
                <td class="label">Date</td>
                <td class="value">{{ $purchase->completed_at?->format('j F Y') }}</td>
            </tr>
            <tr>
                <td class="label">Billed to</td>
                <td class="value">{{ $purchase->user?->email }}</td>
            </tr>
            <tr>
                <td class="label">Introductions</td>
                <td class="value">{{ $purchase->credits_granted }}</td>
            </tr>
            <tr>
                <td class="label">Currency</td>
                <td class="value">{{ $purchase->currency }}</td>
            </tr>
            @if ($purchase->gateway_transaction_id)
                <tr>
                    <td class="label">Transaction</td>
                    {{-- Last four only. The full identifier is not the member's
                         to hold and is not needed to trace a payment. --}}
                    <td class="value">••••{{ substr($purchase->gateway_transaction_id, -4) }}</td>
                </tr>
            @endif
            <tr class="total">
                <td>Total paid</td>
                <td class="value">{{ $purchase->amountFormatted() }}</td>
            </tr>
        </table>

        <hr class="rule">

        {{--
            ⚠️ THE SENTENCE THAT PREVENTS CHARGEBACKS.

            The company taking the money and the name on the card statement
            are different for this product. Somebody who sees an unfamiliar
            name on their statement and cannot connect it to a marriage site
            phones their bank — this line is what stops that, and it has to
            arrive before the statement does, not after.
        --}}
        @if ($billing['statementLine'])
            <p class="billed">{{ $billing['statementLine'] }}</p>
        @endif

        @if ($billing['address'])
            <p class="billed">{{ $billing['address'] }}</p>
        @endif

        @if ($billing['supportEmail'])
            <p class="billed">Questions about this charge: {{ $billing['supportEmail'] }}</p>
        @endif

        <div class="seal" aria-hidden="true">RV</div>

        <p class="foot">
            Your introductions do not expire. Keep this receipt for your records.
        </p>

        <p class="noprint" style="margin-top:24px">
            <button type="button" onclick="window.print()">Save as PDF</button>
        </p>
    </div>
</body>
</html>
