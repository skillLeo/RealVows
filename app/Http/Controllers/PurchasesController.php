<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class PurchasesController extends Controller
{
    public function index(Request $request): Response
    {
        $purchases = Purchase::query()
            ->where('user_id', $request->user()->id)
            // Failed attempts are shown too. Hiding them makes a member who
            // remembers a decline think the record is wrong.
            ->whereIn('status', [
                Purchase::STATUS_COMPLETED,
                Purchase::STATUS_FAILED,
                Purchase::STATUS_REFUNDED,
                Purchase::STATUS_VOIDED,
            ])
            ->latest('id')
            ->paginate(20)
            ->through(fn (Purchase $p) => [
                'reference' => $p->reference(),
                'date' => $p->completed_at?->format('j F Y') ?? $p->created_at->format('j F Y'),
                'amount' => $p->amountFormatted(),
                'credits' => $p->credits_granted,
                'status' => $p->status,
                'succeeded' => $p->isComplete(),

                /*
                 * Signed and long-lived. A receipt somebody cannot open in a
                 * year is not a receipt, so this deliberately outlives the
                 * one-hour expiry used for photographs — a receipt reveals
                 * nothing about another member.
                 */
                'receiptUrl' => $p->isComplete()
                    ? URL::temporarySignedRoute('receipt', now()->addYears(2), ['order' => $p->order_identifier])
                    : null,
            ]);

        return Inertia::render('Purchases', [
            'purchases' => $purchases,
            'balance' => $request->user()->creditBalance(),
        ]);
    }
}
