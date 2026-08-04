<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Services\Payments\PurchaseService;
use App\Support\Packages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    /**
     * ⚠️ THE ROUTE, NOT JUST THE BUTTON.
     *
     * Hiding the packages page removes the way in; this removes the destination.
     * A member with an old link, a bookmark, or a back button would otherwise
     * reach a checkout that cannot complete — which is worse than a paywall,
     * because it takes their attention and then fails.
     */
    private function refuseDuringFreePeriod(): void
    {
        abort_if(app(\App\Support\FreePeriod::class)->isActive(), 404);
    }

    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly Packages $packages,
    ) {}

    /**
     * Order summary. One package, one price, one action.
     *
     * Deliberately nothing else on the screen — no upsell, no cross-sell, no
     * "customers also bought". Somebody about to spend money on finding a
     * husband should not be handled like a shopping cart.
     */
    public function show(Request $request, string $package): Response
    {
        $this->refuseDuringFreePeriod();

        abort_unless(in_array($package, Packages::KEYS, true), 404);

        return Inertia::render('Checkout/Show', [
            'package' => $this->packages->get($package),
            'credits' => $request->user()->creditBalance(),
        ]);
    }

    /**
     * Create the purchase and hand off to the gateway.
     *
     * The purchase row exists before anything is sent anywhere, so a failure
     * mid-handoff leaves a record explaining what happened rather than
     * nothing at all.
     */
    public function start(Request $request, string $package): RedirectResponse
    {
        $this->refuseDuringFreePeriod();

        abort_unless(in_array($package, Packages::KEYS, true), 404);

        $purchase = $this->purchases->initiate($request->user(), $package);

        /*
         * ⚠️ The callback URL is signed and STATELESS. The 3DS return is a
         * cross-site POST and the session cookie will be dropped — see
         * PaymentCallbackController. The signature is what proves the
         * request is about this purchase, since there is no session to ask.
         */
        $callback = URL::signedRoute('checkout.callback', [
            'order' => $purchase->order_identifier,
        ]);

        $handoff = $this->purchases->handOff($purchase, $callback);

        if (! $handoff->ok) {
            return redirect()->route('checkout.result', $purchase->order_identifier);
        }

        if ($handoff->html) {
            // The bank's challenge form, auto-submitting from the processing
            // screen so the member sees a designed page rather than a flash
            // of raw markup.
            return redirect()->route('checkout.processing', $purchase->order_identifier);
        }

        return redirect()->away($handoff->redirectUrl);
    }

    /**
     * The waiting room. Honest copy, a skeleton, and a hard timeout that
     * routes to recovery rather than hanging forever.
     */
    public function processing(Request $request, string $order): Response
    {
        $purchase = $this->ownedPurchase($request, $order);

        return Inertia::render('Checkout/Processing', [
            'order' => $purchase->order_identifier,
            'reference' => $purchase->reference(),
            'challengeHtml' => $purchase->status === Purchase::STATUS_PENDING_3DS
                ? $this->purchases->handOff($purchase, URL::signedRoute('checkout.callback', ['order' => $purchase->order_identifier]))->html
                : null,
            'resultUrl' => route('checkout.result', $purchase->order_identifier),
        ]);
    }

    /**
     * Success routes into the unsealing. Failure gets a calm, specific,
     * non-blaming screen — failed payments are where members decide whether
     * to trust a platform with money, so it matters as much as the success.
     */
    public function result(Request $request, string $order): Response
    {
        $purchase = $this->ownedPurchase($request, $order);

        // One last authoritative check, in case the member arrived here
        // before the callback did.
        if (in_array($purchase->status, Purchase::IN_FLIGHT, true)) {
            $purchase = $this->purchases->settle($purchase);
        }

        return Inertia::render('Checkout/Result', [
            'succeeded' => $purchase->isComplete(),
            'reference' => $purchase->reference(),
            'credits' => $purchase->credits_granted,
            'amount' => $purchase->amountFormatted(),
            'balance' => $request->user()->creditBalance(),
            'failureMessage' => $this->plainEnglishFailure($purchase),
            'packageKey' => $purchase->package_key,
        ]);
    }

    /**
     * Gateway codes mean nothing to a member. What they need to know is
     * whether they were charged, and what to try next.
     */
    private function plainEnglishFailure(Purchase $purchase): ?string
    {
        if ($purchase->isComplete()) {
            return null;
        }

        return match ($purchase->failure_code) {
            '05', '51' => 'Your bank declined the payment. They did not say why, and nothing was charged. A different card usually works.',
            '54' => 'That card has expired. Nothing was charged.',
            '14' => 'That card number was not recognised. Nothing was charged.',
            'timeout' => 'We did not hear back from your bank in time, so we stopped and charged nothing.',
            default => 'The payment did not go through, and nothing was charged. It is worth trying again — if it happens twice, your bank will know why.',
        };
    }

    /**
     * Ownership, not guesswork. A member can only ever see their own order,
     * and a stranger's order identifier answers 404 rather than 403.
     */
    private function ownedPurchase(Request $request, string $order): Purchase
    {
        return Purchase::where('order_identifier', $order)
            ->where('user_id', $request->user()->id)
            ->firstOr(fn () => abort(404));
    }
}
