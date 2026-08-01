<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientCredits;
use App\Exceptions\MatchAlreadyUnlocked;
use App\Models\MatchRecord;
use App\Services\Credits\CreditService;
use App\Support\MatchPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MatchController extends Controller
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly MatchPresenter $presenter,
    ) {}

    /**
     * The full view of one introduction — unlocked only.
     *
     * A teaser answers 404 rather than 403. 403 would confirm the row exists
     * and that there is something worth paying for behind it; 404 is the
     * honest answer as far as an unpaid member is concerned.
     */
    public function show(Request $request, MatchRecord $match): Response
    {
        $match = $this->ownedMatch($request, $match);

        abort_unless($match->isUnlocked(), 404);

        if (! $match->viewed_at) {
            $match->forceFill(['viewed_at' => now()])->save();
        }

        return Inertia::render('Matches/Show', [
            'match' => $this->presenter->full($match),
        ]);
    }

    /**
     * Spend one credit to open one introduction.
     *
     * Both failure modes are kind: no credits is an invitation, and an
     * already-open match reassures the member they were not charged twice.
     */
    public function unlock(Request $request, MatchRecord $match): RedirectResponse
    {
        $match = $this->ownedMatch($request, $match);

        try {
            $this->credits->unlockMatch($request->user(), $match);
        } catch (InsufficientCredits $e) {
            return back()->with('error', $e->getMessage());
        } catch (MatchAlreadyUnlocked $e) {
            return redirect()->route('matches.show', $match)->with('error', $e->getMessage());
        }

        /*
         * The unsealing is presentation only. The credit is already spent and
         * the status is already unlocked before any animation begins — if the
         * member closes the tab mid-ceremony they lose nothing and simply
         * land on an open card next time.
         */
        return redirect()->route('dashboard')->with('unsealed', [$match->id]);
    }

    /**
     * Resolved from the signed-in member, never from the URL alone. There is
     * no id a member can change to see somebody else's introduction.
     */
    private function ownedMatch(Request $request, MatchRecord $match): MatchRecord
    {
        $profileId = $request->user()->profile?->id;

        abort_if($profileId === null, 404);
        abort_unless($match->seeker_profile_id === $profileId, 404);

        return $match;
    }
}
