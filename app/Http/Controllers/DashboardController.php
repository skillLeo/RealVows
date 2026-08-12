<?php

namespace App\Http\Controllers;

use App\Models\MatchRecord;
use App\Models\Verification;
use App\Support\MatchPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly MatchPresenter $presenter,
        private readonly \App\Services\Guardian\GuardianGate $gate,
    ) {}

    /**
     * The member's introductions, in one of five states.
     *
     * ⚠️ There is no browse surface here and there never will be. Every row
     * this returns was written for this one seeker by the engine. No search,
     * no filter, no sort, and no id anybody can enumerate.
     */
    public function index(Request $request): Response
    {
        $user = $request->user()->loadMissing('profile.verification');
        $profile = $user->profile;
        $verification = $profile?->verification;

        /*
         * Eager loaded in one pass, including photos.
         *
         * Loading photos only for unlocked rows would look thriftier and
         * would produce an N+1 the moment a member has a mix of both.
         * MatchPresenter is what decides whether a signed URL is ever
         * generated from them — and for a locked match it never is.
         */
        $matches = $profile
            ? MatchRecord::forSeeker($profile->id)
                ->with(['matched.photos'])
                ->limit(24)
                ->get()
            : collect();

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

            /*
             * ⚠️ THE MIDDLE B5 GATE, ASKED WHERE THE CARDS ARE EMITTED.
             *
             * Where the policy requires a trusted contact, they see the other
             * party first and approve before the member sees anything. So an
             * introduction awaiting that approval is not rendered as a locked
             * card — it is not rendered at all. A locked card is an invitation
             * to spend a credit, and offering one that cannot be opened yet is
             * worse than showing nothing.
             *
             * ⚠️ FILTERED, NOT HIDDEN IN THE COMPONENT. The card payload
             * carries a first name, a city and a score. Rendering it and
             * hiding it in Vue would put all three in the page source of a
             * member who is not yet permitted to see them.
             */
            'matches' => $matches
                ->filter(fn (MatchRecord $m) => $this->gate->mayReleaseIntroduction($request->user(), $m))
                ->map(fn (MatchRecord $m) => $this->presenter->forCard($m))
                ->values()
                ->all(),

            /*
             * ⚠️ NEVER LET A FLOW DEADLOCK WITH NO VISIBLE REASON (B8).
             *
             * A member whose introductions have stopped must be able to see
             * why on their own dashboard. Silence reads as the product being
             * broken, and nobody can chase a person they were never told they
             * were waiting on.
             */
            'trustedContact' => $this->gate->stateFor($request->user()),
            'lockedCount' => $matches->where('status', MatchRecord::STATUS_TEASER)->count(),
            'openCount' => $matches->where('status', MatchRecord::STATUS_UNLOCKED)->count(),

            /*
             * Which cards to perform the unsealing on.
             *
             * Presentation only. The credit is already spent and the status
             * already unlocked in the database before this is read — closing
             * the tab mid-ceremony costs the member nothing.
             */
            'unsealed' => $request->session()->get('unsealed', []),
        ]);
    }
}
