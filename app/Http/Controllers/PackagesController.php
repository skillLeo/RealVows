<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackagesController extends Controller
{
    /**
     * A holding page until Phase 2 brings the purchase path.
     *
     * The tab bar has four tabs and a member will tap this one, so it states
     * the real prices and the free-signup promise rather than 404ing. There
     * is deliberately no "buy" button: a dead checkout would be worse than
     * an honest "not yet".
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Packages', [
            'completeness' => (int) ($request->user()->profile?->completeness_percent ?? 0),
        ]);
    }
}
