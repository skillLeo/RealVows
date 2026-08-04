<?php

namespace App\Http\Controllers;

use App\Support\Packages;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackagesController extends Controller
{
    /**
     * ⚠️ 404 DURING THE FREE PERIOD, NOT A DISABLED PAGE.
     *
     * A disabled button still tells a member there is something they are being
     * denied, and a page that renders with everything greyed out reads as
     * broken rather than free. While there is nothing to buy, the shop does not
     * exist — which is also the only way to guarantee nobody reaches a checkout
     * that cannot complete.
     */
    private function refuseDuringFreePeriod(): void
    {
        abort_if(app(\App\Support\FreePeriod::class)->isActive(), 404);
    }

    public function __construct(private readonly Packages $packages) {}

    /**
     * Three packages, prices read from settings so the client can change them
     * without a deploy.
     *
     * No urgency copy, no countdown, no scarcity. The reassurance panel above
     * the cards is doing real work: somebody who is not ready to pay should
     * leave feeling their profile is safe, not feeling pushed.
     */
    public function index(Request $request): Response
    {
        $this->refuseDuringFreePeriod();

        return Inertia::render('Packages', [
            'packages' => array_values($this->packages->all()),
            'credits' => $request->user()->creditBalance(),
            'completeness' => (int) ($request->user()->profile?->completeness_percent ?? 0),
        ]);
    }
}
