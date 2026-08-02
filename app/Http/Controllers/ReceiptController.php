<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Support\SkillLeo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * A receipt as a real artefact: a server-rendered HTML page at a signed URL,
 * printable to PDF by the browser.
 *
 * ⚠️ Deliberately NOT a PDF library. Shared hosting has no memory to spare
 * and dompdf would be a dependency, a font problem and a rendering
 * difference from the page it is supposed to match. Every browser and phone
 * already prints to PDF perfectly well, and the print stylesheet makes the
 * output correct.
 */
class ReceiptController extends Controller
{
    /**
     * Signed, and additionally ownership-checked.
     *
     * The signature means the URL works from an email client with no session
     * — which is the whole point of putting it in a receipt email. The
     * ownership check means a forwarded link is useless to anybody else.
     */
    public function show(Request $request, string $order): View
    {
        $purchase = Purchase::with('user.profile')
            ->where('order_identifier', $order)
            ->firstOr(fn () => abort(404));

        // A receipt for money that never moved is not a receipt.
        abort_unless($purchase->isComplete(), 404);

        /*
         * If somebody IS signed in, it must be the right somebody. If nobody
         * is signed in, the signature is doing the work — that is how this
         * opens from an email.
         */
        if ($request->user() && $request->user()->id !== $purchase->user_id) {
            abort(404);
        }

        return view('receipt', [
            'purchase' => $purchase,
            'billedBy' => SkillLeo::BILLING,
        ]);
    }
}
