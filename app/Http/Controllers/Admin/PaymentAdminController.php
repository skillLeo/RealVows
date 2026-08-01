<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentAdminController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly PaymentGateway $gateway,
    ) {}

    public function index(Request $request): Response
    {
        $purchases = Purchase::query()
            ->with('user:id,email')
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Purchase $p) => [
                'id' => $p->id,
                'reference' => $p->reference(),
                'email' => $p->user?->email,
                'amount' => $p->amountFormatted(),
                'credits' => $p->credits_granted,
                'status' => $p->status,
                'mode' => $p->gateway_mode,
                'createdAt' => $p->created_at->format('j M Y, H:i'),
                'canRequery' => in_array($p->status, Purchase::IN_FLIGHT, true),
                'canVoid' => $p->status === Purchase::STATUS_AUTHORISED,
            ]);

        return Inertia::render('Admin/Payments', [
            'purchases' => $purchases,
            'filters' => ['status' => $request->string('status')->toString()],
            // ⚠️ Drives the loud banner. Nobody should ever mistake a test
            // transaction for a real one, or worse, the other way round.
            'mode' => $this->gateway->mode(),
        ]);
    }

    /** Ask the gateway what actually happened. Never trust our own record. */
    public function requery(Purchase $purchase): RedirectResponse
    {
        $this->purchases->settle($purchase);

        return back()->with('success', "Re-queried {$purchase->reference()}. Status is now {$purchase->fresh()->status}.");
    }

    public function void(Purchase $purchase): RedirectResponse
    {
        if (! $this->gateway->void($purchase)) {
            return back()->with('error', 'The gateway would not void that transaction.');
        }

        $purchase->forceFill(['status' => Purchase::STATUS_VOIDED])->save();

        return back()->with('success', "Voided {$purchase->reference()}.");
    }
}
