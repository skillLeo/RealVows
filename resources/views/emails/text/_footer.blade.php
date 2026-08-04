@php
    $billing = app(\App\Support\Billing::class);
    $entity = $billing->isConfigured()
        ? ($billing->tradingAsLine() ?? $billing->legalEntity())
        : '[PENDING — the contracting entity is not yet confirmed]';
@endphp
--
RealVows · marriage-first · 18+
{{ $entity }}

To stop receiving these emails: {{ $unsubscribeUrl }}
