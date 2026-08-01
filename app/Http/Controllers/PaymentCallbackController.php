<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Services\Payments\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where the issuing bank sends the member back after a 3DS challenge.
 *
 * ⚠️ THIS ROUTE IS STATELESS ON PURPOSE. No `web` middleware, no session, no
 * CSRF.
 *
 * The 3DS return is a cross-site POST. Laravel's default SESSION_SAME_SITE
 * of `lax` means the session cookie is NOT sent on it, so the member appears
 * signed out at the exact moment they come back from their bank. The wrong
 * fix is SameSite=none, which weakens every cookie on the platform to solve
 * one route. The right fix is this: identify the purchase from our own
 * order_identifier, do the work without needing to know who is signed in,
 * then bounce to a signed URL on the session-bearing side which
 * re-establishes context.
 *
 * ⚠️ This will pass on localhost and fail on the live domain if it is ever
 * "simplified" back into the web group. There is a test for it.
 *
 * ⚠️ NOTHING IN THE REQUEST BODY IS TRUSTED. There is no signed webhook
 * here, unlike Stripe — the POST is not proof of anything. It is treated as
 * a signal meaning "go and ask the gateway now", and the gateway's answer is
 * the only thing that grants a credit.
 */
class PaymentCallbackController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases) {}

    public function __invoke(Request $request, string $order): Response
    {
        $purchase = Purchase::where('order_identifier', $order)->first();

        if (! $purchase) {
            /*
             * A callback for an order we have never heard of. Log it and
             * answer 200: arguing with a payment gateway earns retries, and
             * there is genuinely nothing to do.
             */
            report(new \RuntimeException("Payment callback for unknown order [{$order}]."));

            return response('OK', 200);
        }

        $this->purchases->record(
            $purchase,
            'inbound',
            'callback',
            $request->all(),
            note: 'Signal only — status re-queried from the gateway.'
        );

        try {
            $this->purchases->settle($purchase);
        } catch (\Throwable $e) {
            report($e);
        }

        /*
         * Back to the session-bearing side. Signed, because at this point we
         * still have no session and this URL is the only thing carrying the
         * member forward.
         */
        return redirect()->to(URL::signedRoute('checkout.return', [
            'order' => $purchase->order_identifier,
        ]));
    }

    /**
     * The landing back inside the session. Redirects to the ordinary result
     * screen, which is where the unsealing fires.
     */
    public function return(Request $request, string $order): Response
    {
        return redirect()->route('checkout.result', $order);
    }
}
