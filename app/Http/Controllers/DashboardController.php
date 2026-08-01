<?php

namespace App\Http\Controllers;

use App\Models\Verification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Phase 1 shell: completeness, verification status, credits.
     *
     * No match cards and no locked teaser cards — those are Phase 2. What a
     * member sees here instead is a designed empty state, because a blank
     * screen is the least welcoming thing a new member can meet.
     */
    public function index(Request $request): Response
    {
        $user = $request->user()->loadMissing('profile.verification');
        $profile = $user->profile;
        $verification = $profile?->verification;

        return Inertia::render('Dashboard', [
            // Explicit fields only. Never the whole Profile model: `sign` and
            // `element` are internal, and hiding them on the model is the
            // safety net, not the plan.
            'completeness' => (int) ($profile?->completeness_percent ?? 0),
            'firstName' => $profile?->first_name,
            'credits' => $user->creditBalance(),
            'verification' => [
                'status' => $verification?->status ?? 'not_started',
                'approvedAt' => $verification?->status === Verification::STATUS_VERIFIED
                    ? $verification->reviewed_at?->format('j F')
                    : null,
                'rejectionReason' => $verification?->status === Verification::STATUS_REJECTED
                    ? $verification->rejection_reason
                    : null,
            ],
        ]);
    }
}
