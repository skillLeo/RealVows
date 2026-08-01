<?php

namespace App\Http\Controllers;

use App\Support\Packages;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackagesController extends Controller
{
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
        return Inertia::render('Packages', [
            'packages' => array_values($this->packages->all()),
            'credits' => $request->user()->creditBalance(),
            'completeness' => (int) ($request->user()->profile?->completeness_percent ?? 0),
        ]);
    }
}
