@php
    $billing = app(\App\Support\Billing::class);
    $entity = $billing->isConfigured()
        ? ($billing->tradingAsLine() ?? $billing->legalEntity())
        : 'The contracting entity is not yet confirmed.';
@endphp
--
RealVows · soul mate first · 18+
{{ $entity }}

To stop receiving these emails: {{ $unsubscribeUrl }}
