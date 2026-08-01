<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use Illuminate\Http\Request;
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
            ]);

        return Inertia::render('Purchases', [
            'purchases' => $purchases,
            'balance' => $request->user()->creditBalance(),
        ]);
    }
}
